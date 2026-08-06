<?php

namespace App\Events\Finding;

use App\Models\Finding;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FindingCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Finding $finding) {}
}
