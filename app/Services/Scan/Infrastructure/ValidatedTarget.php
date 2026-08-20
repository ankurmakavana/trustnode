<?php

namespace App\Services\Scan\Infrastructure;

class ValidatedTarget
{
    public string $original;
    public string $ip;
    public bool $isDomain;

    public function __construct(string $original, string $ip, bool $isDomain)
    {
        $this->original = $original;
        $this->ip = $ip;
        $this->isDomain = $isDomain;
    }
}
