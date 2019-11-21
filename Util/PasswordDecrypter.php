<?php

namespace Truonglv\Api\Util;

class PasswordDecrypter
{
    const ALGO_AES_128_ECB = 'AES-128-ECB';

    /**
     * @param string $payload
     * @param string $key
     * @return string
     */
    public static function encrypt($payload, $key)
    {
        if (\trim($key) === '') {
            throw new \InvalidArgumentException('Key must not empty!');
        }

        $key = \md5($key, true);
        $encrypted = \openssl_encrypt($payload, self::ALGO_AES_128_ECB, $key, OPENSSL_RAW_DATA);
        if ($encrypted === false) {
            throw new \InvalidArgumentException('Cannot encrypt data!');
        }

        return \base64_encode($encrypted);
    }

    /**
     * @param string $encrypted
     * @param string $key
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function decrypt($encrypted, $key)
    {
        if (\trim($key) === '') {
            throw new \InvalidArgumentException('Key must not empty!');
        }

        $encrypted = \base64_decode($encrypted, true);
        if ($encrypted === false) {
            throw new \InvalidArgumentException('Encrypted string must not empty!');
        }

        $keyHashed = \md5($key, true);

        $decrypted = \openssl_decrypt($encrypted, self::ALGO_AES_128_ECB, $keyHashed, OPENSSL_RAW_DATA);
        if ($decrypted === false) {
            throw new \InvalidArgumentException('Encrypted string must not empty!');
        }

        return $decrypted;
    }
}
