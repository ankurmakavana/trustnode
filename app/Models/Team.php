<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'organization_id',
        'description',
    ];

    protected static function booted()
    {
        static::creating(function ($team) {
            if (empty($team->uuid)) {
                $team->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the organization this team belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the users in this team.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user');
    }
}
