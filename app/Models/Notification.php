<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;
use App\Models\Scopes\TenantScope;

class Notification extends DatabaseNotification
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
        'created_by',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
        
        static::creating(function ($notification) {
            if (empty($notification->created_by) && auth()->check()) {
                $notification->created_by = auth()->id();
            }
        });
    }
}
