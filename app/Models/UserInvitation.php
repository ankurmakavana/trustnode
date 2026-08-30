<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserInvitation extends Model
{
    protected $fillable = [
        'uuid',
        'email',
        'token_hash',
        'role_id',
        'invited_by',
        'created_by_ip',
        'accepted_by_ip',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\TenantScope);
        static::creating(function ($invitation) {
            if (empty($invitation->uuid)) {
                $invitation->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Get the role designated for the invited user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the user who sent the invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Determine if the invitation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Determine if the invitation is active and valid.
     */
    public function isValid(): bool
    {
        return ! $this->isExpired() && is_null($this->accepted_at) && is_null($this->revoked_at);
    }
}
