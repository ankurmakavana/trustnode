<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Report extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'report_id',
        'title',
        'type',
        'status',
        'options',
        'created_by',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Report $report) {
            if (empty($report->uuid)) {
                $report->uuid = (string) Str::uuid();
            }
            if (empty($report->report_id)) {
                $latest = self::orderBy('id', 'desc')->first();
                $nextNum = $latest ? ($latest->id + 1) : 1;
                $report->report_id = 'TR-REP-2026-'.str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ReportHistory::class)->orderBy('created_at', 'desc');
    }

    protected static function booted()
    {
        parent::booted();
        static::addGlobalScope(new \App\Models\Scopes\TenantScope);
    }
}
