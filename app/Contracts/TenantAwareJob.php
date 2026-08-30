<?php

namespace App\Contracts;

interface TenantAwareJob
{
    /**
     * Get the tenant ID associated with the job.
     */
    public function getTenantId(): ?int;
}
