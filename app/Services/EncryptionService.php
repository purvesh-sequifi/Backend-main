<?php

namespace App\Services;

class EncryptionService
{
    /**
     * Encrypt a string using OpenSSL
     *
     * @param  string  $data  Data to encrypt
     * @return string|false Encrypted data or false on failure
     */
    public static function encrypt(string $data)
    {
        $cipher = config('app.encryption_cipher_algo');
        $key = config('app.encryption_key');
        $iv = config('app.encryption_iv');

        if (empty($key) || empty($iv)) {
            \Log::error('Encryption failed: Missing encryption key or IV');

            return false;
        }

        return openssl_encrypt($data, $cipher, $key, 0, $iv);
    }

    /**
     * Decrypt a string using OpenSSL
     *
     * @param  string  $encryptedData  Encrypted data to decrypt
     * @return string|false Decrypted data or false on failure
     */
    public static function decrypt(string $encryptedData)
    {
        $cipher = config('app.encryption_cipher_algo');
        $key = config('app.encryption_key');
        $iv = config('app.encryption_iv');

        if (empty($key) || empty($iv)) {
            \Log::error('Decryption failed: Missing encryption key or IV');

            return false;
        }

        return openssl_decrypt($encryptedData, $cipher, $key, 0, $iv);
    }
}
