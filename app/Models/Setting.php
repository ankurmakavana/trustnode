<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $with = ['organization'];

    protected $fillable = [
        'key',
        'value',
        'organization_id',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new \App\Models\Scopes\TenantScope);
    }

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
     * Installation-wide read. Keys are unique, so a key has one row whichever organization wrote it.
     * Runtime processes (queue workers, the scheduler, boot) have no user, so TenantScope would hide
     * the row. Use this only for installation-wide keys such as mail configuration.
     */
    public static function globalValues(array $keys): array
    {
        return static::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
            ->whereIn('key', $keys)
            ->get()
            ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->decrypted_value])
            ->all();
    }

    /**
     * Get the organization this setting belongs to.
     */
    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Helper to set a setting value.
     */
    public static function setValue($key, $value, $encrypt = false)
    {
        $storeValue = $encrypt && $value ? Crypt::encryptString($value) : $value;

        // Settings are organization-owned. A row without an organization is hidden from every user by
        // TenantScope, so the authenticated user's team organization is assigned on write, as for scans.
        $organizationId = \Illuminate\Support\Facades\Auth::user()?->teams()->first()?->organization_id;

        $attributes = [
            'value' => $storeValue,
            'is_encrypted' => $encrypt,
        ];

        // Without an authenticated user, the existing row's organization is left unchanged.
        if ($organizationId !== null) {
            $attributes['organization_id'] = $organizationId;
        }

        return static::updateOrCreate(['key' => $key], $attributes);
    }
}
