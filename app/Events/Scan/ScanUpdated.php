<?php

namespace App\Events\Scan;

use App\Models\Scan;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScanUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Scan $scan) {}
}
