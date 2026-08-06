<?php

namespace App\Events\Scan;

use App\Models\Scan;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScanDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Scan $scan) {}
}
