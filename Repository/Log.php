<?php

namespace Truonglv\Api\Repository;

use Truonglv\Api\App;
use XF\Http\Request;
use XF\Http\Response;
use XF\Http\ResponseFile;
use XF\Http\ResponseStream;
use XF\Mvc\Entity\Repository;

class Log extends Repository
{
    const MAX_ARRAY_ELEMENTS = 10;
    const MAX_STRING_LENGTH = 200;

    public function log(Request $request, Response $response): void
    {
        $enableLogging = (bool) $this->options()->tApi_enableLogRequests;
        if (!$enableLogging) {
            return;
        }

        /** @var \Truonglv\Api\Entity\Log $log */
        $log = $this->app()->em()->create('Truonglv\Api:Log');
        $log->user_id = \XF::visitor()->user_id;
        $log->app_version = '';
        $log->end_point = $request->getRequestUri();
        $log->method = \strtoupper($request->getRequestMethod());

        $post = $_POST;
        if (isset($post['password'])) {
            $post['password'] = '******';
        }

        $log->payload = [
            '_POST' => $post
        ];

        $log->response_code = $response->httpCode();
        $body = $response->body();
        if ($body instanceof ResponseFile
            || $body instanceof ResponseStream
        ) {
            $log->response = '';
        } else {
            $log->response = trim($this->prepareDataForLog($body));
        }

        $log->save();
    }

    /**
     * @param string $data
     * @return string
     */
    public function prepareDataForLog(string $data): string
    {
        $json = \json_decode($data, true);
        if (!\is_array($json)) {
            return $this->prepareValueForLogging($data);
        }

        $results = [];
        foreach ($json as $key => $value) {
            if (\is_array($value)) {
                $results[$key] = $this->prepareArrayForLogging($value);
            } else {
                $results[$key] = $this->prepareValueForLogging($value);
            }
        }

        return (string) \json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function prepareValueForLogging($value)
    {
        if (\is_string($value)) {
            if (\strlen($value) > self::MAX_STRING_LENGTH) {
                $value = \substr($value, 0, self::MAX_STRING_LENGTH) . ' (STRIPPED)';
            }

            return $value;
        }

        return $value;
    }

    /**
     * @param array $data
     * @param int $depth
     * @return array
     */
    protected function prepareArrayForLogging(array $data, int $depth = 1)
    {
        if ($depth > 3) {
            return ['(...) (Too many depths)'];
        }

        $keys = \array_keys($data);
        $arrayStripped = 0;

        if (\count($keys) > self::MAX_ARRAY_ELEMENTS) {
            $arrayStripped = \count($keys) - self::MAX_ARRAY_ELEMENTS;
            $keys = \array_slice($keys, 0, self::MAX_ARRAY_ELEMENTS);
        }

        $results = [];
        foreach ($keys as $key) {
            $value = $data[$key];
            if (\is_array($value)) {
                $results[$key] = $this->prepareArrayForLogging($value, $depth + 1);
            } else {
                $results[$key] = $this->prepareValueForLogging($value);
            }
        }

        if ($arrayStripped > 0) {
            $results['(...)'] = '(Stripped ' . $arrayStripped . ' elements)';
        }

        return $results;
    }
}
