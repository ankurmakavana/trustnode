<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanReport extends Model
{
    protected $fillable = [
        'scan_id', 'requested_by', 'status', 'progress', 'file_path', 'error_message', 'started_at', 'completed_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new \App\Models\Scopes\TenantScope);
    }

    public function scan()
    {
        return $this->belongsTo(Scan::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
