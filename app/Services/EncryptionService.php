<?php

namespace App\Services;

class EncryptionService
{
    /**
     * Encrypt binary content using AES-256-CBC.
     * Returns: ['ciphertext' => string, 'key' => string, 'iv' => string]
     */
    public static function encrypt(string $plainText): array
    {
        // 1. Generate key and IV
        $key = random_bytes(32); // 256 bits
        $iv  = random_bytes(16); // 128 bits

        // 2. Encrypt
        $cipherText = openssl_encrypt(
            $plainText,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return [
            'ciphertext' => $cipherText,
            'key'        => base64_encode($key),
            'iv'         => base64_encode($iv),
        ];
    }

    /**
     * Decrypt content using AES-256-CBC, a base64 encoded key, and a base64 encoded IV.
     */
    public static function decrypt(string $cipherText, string $encodedKey, string $encodedIv): ?string
    {
        $key = base64_decode($encodedKey);
        $iv  = base64_decode($encodedIv);

        $decrypted = openssl_decrypt(
            $cipherText,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $decrypted !== false ? $decrypted : null;
    }
}
