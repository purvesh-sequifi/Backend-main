<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'key',
        'value',
        'user_id',
        'is_encrypted',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function getValueAttribute($value)
    {
        if ($this->is_encrypted && in_array($this->key, [
            'AWS_ACCESS_KEY_ID_PUBLIC',
            'AWS_SECRET_ACCESS_KEY_PUBLIC',
            'AWS_ACCESS_KEY_ID_PRIVATE',
            'AWS_SECRET_ACCESS_KEY_PRIVATE',
        ])) {
            $method = config('app.encryption_cipher_algo', 'aes-256-cbc');
            $key = config('app.encryption_key');
            $iv = config('app.encryption_iv');
            
            // If encryption variables are not set, return the data as-is
            if (empty($method) || empty($key) || empty($iv)) {
                \Log::warning('Settings encryption variables not set, returning data as-is', [
                    'setting_key' => $this->key,
                    'method' => $method ? 'set' : 'missing',
                    'key' => $key ? 'set' : 'missing',
                    'iv' => $iv ? 'set' : 'missing'
                ]);
                return $value;
            }
            
            $decrypted = openssl_decrypt($value, $method, $key, 0, $iv);
            
            // If decryption fails, return original data
            if ($decrypted === false) {
                \Log::warning('Settings decryption failed, returning original data', [
                    'setting_key' => $this->key
                ]);
                return $value;
            }
            
            return $decrypted;
        }

        return $value;
    }
}
