<?php

namespace App\Events\Target;

use App\Models\Target;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TargetDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Target $target) {}
}
