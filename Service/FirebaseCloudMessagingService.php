<?php

namespace Truonglv\Api\Service;

use XF;
use Throwable;
use XF\Entity\User;
use function strlen;
use function strval;
use function sprintf;
use function file_exists;
use function is_readable;
use function str_starts_with;
use InvalidArgumentException;
use function file_get_contents;
use Truonglv\Api\Entity\Subscription;

class FirebaseCloudMessagingService extends AbstractPushNotification
{
    private ?string $token = null;
    private ?array $config = null;

    protected function getProviderId(): string
    {
        return 'fcm';
    }

    protected function getToken(): string
    {
        if ($this->token === null) {
            $config = $this->getConfig();

            $now = time();
            $header = ['alg' => 'RS256', 'typ' => 'JWT'];
            $claimSet = [
                'iss' => $config['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $base64UrlHeader = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
            $base64UrlClaim = rtrim(strtr(base64_encode(json_encode($claimSet)), '+/', '-_'), '=');
            $dataToSign = $base64UrlHeader . '.' . $base64UrlClaim;

            // Sign using openssl
            openssl_sign($dataToSign, $signature, $config['private_key'], 'sha256');
            $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

            $jwt = $base64UrlHeader . '.' . $base64UrlClaim . '.' . $base64UrlSignature;

            $client = $this->client();
            $resp = $client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt
                ]
            ]);

            $tokenData = json_decode($resp->getBody()->getContents(), true);
            if (!isset($tokenData['access_token'])) {
                throw new InvalidArgumentException('failed to retrieve access token');
            }

            $this->token = $tokenData['access_token'];
        }

        return $this->token;
    }

    protected function getConfig(): array
    {
        if ($this->config === null) {
            $fbConfigFile = $this->app->config('tApi_firebaseConfigPath');
            if ($fbConfigFile === null) {
                $fbConfigFile = $this->app->options()->tApi_firebaseConfigPath;
            }
            if (strlen($fbConfigFile) === 0) {
                throw new InvalidArgumentException('no firebase config file');
            }

            if (!file_exists($fbConfigFile) || !is_readable($fbConfigFile)) {
                throw new InvalidArgumentException('Firebase config file not exists or not readable.');
            }

            $contents = file_get_contents($fbConfigFile);
            if ($contents === false) {
                throw new InvalidArgumentException('Cannot read Firebase config.');
            }

            $this->config = json_decode($contents, true);
        }

        return $this->config;
    }

    protected function doSendNotification(XF\Mvc\Entity\AbstractCollection $subscriptions, string $title, string $body, array $data): void
    {
        $messages = [];
        $dataTransformed = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (count($value) === 0) {
                    continue;
                }

                $value = json_encode($value);
            }
            $dataTransformed[strval($key)] = strval($value);
        }

        $subsKeyedToken = [];
        if (count($dataTransformed) === 0) {
            $dataTransformed = null;
        }

        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            $subsKeyedToken[$subscription->device_token][] = $subscription;

            $message = [
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $dataTransformed,
                'token' => $subscription->device_token,
                'fcm_options' => [
                    'analytics_label' => $subscription->device_type,
                ]
            ];

            // @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages

            /** @var User $receiver */
            $receiver = $subscription->User;
            if ($subscription->device_type === 'ios') {
                $message['apns'] = [
                    'payload' => [
                        'aps' => [
                            'alert' => $title,
                            'body' => $body,
                            'badge' => $this->getTotalUnviewedNotifications($receiver),
                            'sound' => 'default',
                            'content-available' => 1
                        ]
                    ]
                ];
            } elseif ($subscription->device_type === 'android') {
                $message['android'] = [
                    'priority' => 'high',
                    'sound' => 'default',
                    'notification' => [
                        'notification_count' => $this->getTotalUnviewedNotifications($receiver),
                        'notification_priority' => 'PRIORITY_DEFAULT'
                    ]
                ];
            }

            $messages[] = $message;
        }

        $config = $this->getConfig();
        $token = $this->getToken();

        $this->app->error()->logError(sprintf('sending %d messages', \count($messages)));
        foreach ($messages as $message) {
            try {
                $resp = $this->client()->post(
                    sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', $config['project_id']),
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $token,
                        ],
                        'json' => [
                            'message' => $message,
                        ],
                        'http_errors' => false
                    ]
                );
                if ($resp->getStatusCode() >= 500) {
                    continue;
                }

                if ($resp->getStatusCode() >= 400) {
                    $respData = json_decode($resp->getBody()->getContents(), true);
                    if ($this->isInvalidToken($respData)) {
                        /** @var Subscription $sub */
                        foreach ($subsKeyedToken[$message['token']] as $sub) {
                            $sub->delete(false);
                        }
                    } else {
                        throw new InvalidArgumentException('failed to sent message: ' . json_encode($respData));
                    }
                }
            } catch (Throwable $e) {
                $this->app->logException($e);
            }
        }
    }

    protected function isInvalidToken(array $response): bool
    {
        if (isset($response['error'], $response['error']['message'])) {
            return str_starts_with($response['error']['message'], 'The registration token is not a valid FCM registration token');
        }

        return false;
    }

    public function unsubscribe(string $externalId, string $pushToken): void
    {
    }
}
