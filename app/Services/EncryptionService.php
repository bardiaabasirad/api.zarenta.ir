<?php

namespace App\Services;

class EncryptionService
{
    private static string $cipher = 'AES-256-CBC';

    private static function getKey(): string
    {
        return config('app.encryption_key');
    }

    public static function encrypt(mixed $data): array
    {
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$cipher));

        $encrypted = openssl_encrypt(
            $jsonData,
            self::$cipher,
            self::getKey(),
            OPENSSL_RAW_DATA,  // 👈 این تغییر اصلی است
            $iv
        );

        return [
            'data' => base64_encode($encrypted),
            'iv' => base64_encode($iv)
        ];
    }

    public static function decrypt(string $encryptedData, string $iv): mixed
    {
        $decrypted = openssl_decrypt(
            base64_decode($encryptedData),
            self::$cipher,
            self::getKey(),
            OPENSSL_RAW_DATA,  // 👈 اینجا هم باید باشد
            base64_decode($iv)
        );

        return json_decode($decrypted, true);
    }
}
