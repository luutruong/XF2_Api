<?php

namespace Truonglv\Api\Util;

class PasswordDecrypter
{
    const ALGO_AES_128_ECB = 'AES-128-ECB';

    /**
     * @param string $encrypted
     * @param string $key
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function decrypt($encrypted, $key)
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Key must not empty!');
        }

        $encrypted = \base64_decode($encrypted, true);
        if (empty($encrypted)) {
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
