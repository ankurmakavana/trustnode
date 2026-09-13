<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasPermissions, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
        'role_id',
        'status',
        'password_changed_at',
        'last_login_at',
        'last_login_ip',
        'timezone',
        'locale',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'status' => UserStatus::class,
            'preferences' => 'array',
        ];
    }

    /**
     * Get a specific preference value with fallback to default.
     */
    public function getPreference(string $key, $default = true)
    {
        if (!is_array($this->preferences)) {
            return $default;
        }

        return $this->preferences[$key] ?? $default;
    }

    /**
     * Get the role associated with the user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the teams this user belongs to.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user');
    }

    /**
     * Check if the user belongs to a team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    /**
     * Check if the user belongs to any teams in an organization.
     */
    public function hasAccessToOrganization(Organization $organization): bool
    {
        return $this->teams()
            ->where('organization_id', $organization->id)
            ->exists();
    }

    /**
     * Get all organizations this user has access to via team membership.
     * Returns a Builder that can be used to query organizations.
     */
    public function organizations()
    {
        return Organization::whereIn(
            'id',
            $this->teams()->pluck('teams.organization_id')->unique()
        );
    }

    /**
     * Get invitations sent by this user.
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class, 'invited_by');
    }

    /**
     * Get the entity's notifications.
     */
    public function notifications()
    {
        return $this->morphMany(\App\Models\Notification::class, 'notifiable')->latest();
    }
}
