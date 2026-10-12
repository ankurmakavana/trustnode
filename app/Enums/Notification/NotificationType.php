<?php

namespace App\Enums\Notification;

enum NotificationType: string
{
    case SCAN_COMPLETED = 'scan_completed';
    case SCAN_FAILED = 'scan_failed';
    case SYSTEM_ALERT = 'system_alert';
    case USER_INVITATION = 'user_invitation';
    case REPORT_READY = 'report_ready';
    case REPORT_FAILED = 'report_failed';
    case AGENT_STARTED = 'agent_started';
    case AGENT_STOPPED = 'agent_stopped';
    case AGENT_FAILED = 'agent_failed';
    case PERMISSION_REQUESTED = 'permission_requested';
    case HIGH_SEVERITY_FINDING = 'high_severity_finding';
}
