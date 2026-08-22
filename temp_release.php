<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Release extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'version',
        'artifact_path',
        'artifact_filename',
        'artifact_size',
        'checksum',
        'release_notes',
        'is_active',
        'is_latest',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_latest' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
