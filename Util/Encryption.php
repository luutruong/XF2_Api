<?php

namespace Truonglv\Api\Util;

use XF\Util\Random;
use function in_array;
use InvalidArgumentException;

class Encryption
{
    const ALGO_AES_256_CBC = 'AES-256-CBC';
    const ALGO_AES_BASE64 = 'base64';

    const ITERATIONS = 999;

    /**
     * @param mixed $payload
     * @param string $key
     * @return string
     */
    public static function encrypt($payload, string $key)
    {
        if (strlen($key) === 0) {
            throw new InvalidArgumentException('Key must not empty!');
        }

        $ivLength = openssl_cipher_iv_length(self::ALGO_AES_256_CBC);
        $iv = Random::getRandomString($ivLength);
        if ($iv === false) {
            throw new InvalidArgumentException('Cannot make random IV');
        }
        $salt = Random::getRandomString(256);
        if ($salt === false) {
            throw new InvalidArgumentException('Cannot make salt');
        }

        $value = openssl_encrypt(
            $payload,
            self::ALGO_AES_256_CBC,
            self::getKeyBin($key, $salt),
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($value === false) {
            throw new InvalidArgumentException('Cannot encrypt data');
        }

        $encoded = json_encode([
            'iv' => base64_encode($iv),
            'salt' => base64_encode($salt),
            'value' => base64_encode($value),
        ]);
        if ($encoded === false) {
            throw new InvalidArgumentException('Cannot encode data');
        }

        return \base64_encode($encoded);
    }

    public static function isSupportedAlgo(string $algo): bool
    {
        return in_array($algo, [static::ALGO_AES_256_CBC, static::ALGO_AES_BASE64], true);
    }

    public static function decrypt(string $encrypted, string $key, string $algo = self::ALGO_AES_256_CBC): string
    {
        if (strlen($key) === 0) {
            throw new InvalidArgumentException('Key must not empty!');
        }

        if (!static::isSupportedAlgo($algo)) {
            throw new InvalidArgumentException('Invalid algo');
        }

        $decoded = base64_decode($encrypted, true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Bad encrypted string');
        }

        if ($algo === self::ALGO_AES_BASE64) {
            return $decoded;
        }

        $payload = json_decode($decoded, true);
        if (!self::isValidPayload($payload)) {
            throw new InvalidArgumentException('Bad payload');
        }

        $value = base64_decode($payload['value'], true);
        if ($value === false) {
            throw new InvalidArgumentException('Bad encrypted value');
        }
        $salt = base64_decode($payload['salt'], true);
        if ($salt === false) {
            throw new InvalidArgumentException('Bad salt value');
        }

        $decrypted = openssl_decrypt(
            $value,
            self::ALGO_AES_256_CBC,
            self::getKeyBin($key, $salt),
            OPENSSL_RAW_DATA,
            $payload['iv']
        );
        if ($decrypted === false) {
            throw new InvalidArgumentException('Cannot decrypt data');
        }

        return $decrypted;
    }

    protected static function getKeyBin(string $key, string $salt): string
    {
        $keyHashed = hash_pbkdf2('sha512', $key, $salt, self::ITERATIONS, 64);
        $keyHex = hex2bin($keyHashed);
        if ($keyHex === false) {
            throw new InvalidArgumentException('Cannot hex key');
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
