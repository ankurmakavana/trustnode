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
}
