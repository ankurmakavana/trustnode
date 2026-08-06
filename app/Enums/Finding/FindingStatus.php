<?php

namespace App\Enums\Finding;

enum FindingStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case REMEDIATED = 'remediated';
    case FALSE_POSITIVE = 'false_positive';
    case RISK_ACCEPTED = 'risk_accepted';
}
