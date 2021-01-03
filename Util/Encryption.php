<?php

namespace Truonglv\Api\Util;

use XF\Util\Random;

class Encryption
{
    const ALGO_AES_256_CBC = 'AES-256-CBC';

    const ITERATIONS = 999;

    /**
     * @param mixed $payload
     * @param string $key
     * @return string
     */
    public static function encrypt($payload, string $key)
    {
        if (\trim($key) === '') {
            throw new \InvalidArgumentException('Key must not empty!');
        }

        $ivLength = openssl_cipher_iv_length(self::ALGO_AES_256_CBC);
        $iv = Random::getRandomString($ivLength);
        if ($iv === false) {
            throw new \InvalidArgumentException('Cannot make random IV');
        }
        $salt = Random::getRandomString(256);
        if ($salt === false) {
            throw new \InvalidArgumentException('Cannot make salt');
        }

        $value = openssl_encrypt(
            $payload,
            self::ALGO_AES_256_CBC,
            self::getKeyBin($key, $salt),
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($value === false) {
            throw new \InvalidArgumentException('Cannot encrypt data');
        }

        return \base64_encode(json_encode([
            'iv' => base64_encode($iv),
            'salt' => base64_encode($salt),
            'value' => base64_encode($value),
        ]));
    }

    /**
     * @param string $encrypted
     * @param string $key
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public static function decrypt(string $encrypted, string $key)
    {
        $decoded = base64_decode($encrypted, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Bad encrypted string');
        }

        $payload = json_decode($decoded, true);
        if (!self::isValidPayload($payload)) {
            throw new \InvalidArgumentException('Bad payload');
        }

        $value = base64_decode($payload['value'], true);
        if ($value === false) {
            throw new \InvalidArgumentException('Bad encrypted value');
        }
        $salt = base64_decode($payload['salt'], true);
        if ($salt === false) {
            throw new \InvalidArgumentException('Bad salt value');
        }

        $decrypted = openssl_decrypt(
            $value,
            self::ALGO_AES_256_CBC,
            self::getKeyBin($key, $salt),
            OPENSSL_RAW_DATA,
            $payload['iv']
        );
        if ($decrypted === false) {
            throw new \InvalidArgumentException('Cannot decrypt data');
        }

        return $decrypted;
    }

    protected static function getKeyBin(string $key, string $salt): string
    {
        $keyHashed = hash_pbkdf2('sha512', $key, $salt, self::ITERATIONS, 64);
        $keyHex = hex2bin($keyHashed);
        if ($keyHex === false) {
            throw new \InvalidArgumentException('Cannot hex key');
        }

        return $keyHex;
    }

    /**
     * @param mixed $payload
     * @return bool
     */
    protected static function isValidPayload(&$payload): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        if (isset($payload['iv'])) {
            $payload['iv'] = base64_decode($payload['iv'], true);
        } else {
            return false;
        }

        return isset($payload['salt'])
            && isset($payload['value'])
            && strlen($payload['iv']) === openssl_cipher_iv_length(self::ALGO_AES_256_CBC);
    }
}
