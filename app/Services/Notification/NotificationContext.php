<?php

namespace App\Services\Notification;

use App\Models\Repository;
use App\Models\Scan;
use App\Models\ScanReport;
use App\Models\User;

class NotificationContext
{
    public function __construct(
        public ?Scan $scan = null,
        public ?Repository $repository = null,
        public ?ScanReport $report = null,
        public ?User $initiator = null,
        public ?array $meta = []
    ) {
    }
}
