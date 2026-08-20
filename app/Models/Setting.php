<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Get the decrypted value if the setting is encrypted.
     */
    public function getDecryptedValueAttribute()
    {
        if ($this->is_encrypted && $this->value) {
            try {
                return Crypt::decryptString($this->value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return $this->value;
    }

    /**
     * Helper to get a setting value.
     */
    public static function getValue($key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }
        return $setting->decrypted_value;
    }

    /**
     * Helper to set a setting value.
     */
    public static function setValue($key, $value, $encrypt = false)
    {
        $storeValue = $encrypt && $value ? Crypt::encryptString($value) : $value;
        
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storeValue,
                'is_encrypted' => $encrypt,
            ]
        );
    }
}
