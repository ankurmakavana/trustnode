<?php

namespace App\Events\Asset;

use App\Models\Asset;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssetUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Asset $asset, public array $changes) {}
}
