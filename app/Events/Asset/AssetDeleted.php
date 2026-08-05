<?php

namespace App\Events\Asset;

use App\Models\Asset;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssetDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Asset $asset) {}
}
