<?php

namespace App\Events\Target;

use App\Models\Target;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TargetCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Target $target) {}
}
