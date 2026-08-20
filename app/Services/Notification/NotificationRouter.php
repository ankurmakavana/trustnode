<?php

namespace App\Services\Notification;

use App\Enums\Notification\NotificationType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class NotificationRouter
{
    /**
     * Dispatch a notification to the appropriately resolved recipients.
     */
    public static function dispatch(NotificationType $type, NotificationContext $context, Notification $notification): void
    {
        $recipients = self::resolve($type, $context);

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }
    }

    /**
     * Resolve unique eligible recipients for a given notification type and context.
     */
    public static function resolve(NotificationType $type, NotificationContext $context): Collection
    {
        $recipients = collect();

        switch ($type) {
            case NotificationType::SCAN_COMPLETED:
                $recipients = $recipients->merge(self::getScanInitiators($context));
                $recipients = $recipients->merge(self::getRepositoryOwners($context));
                break;

            case NotificationType::SCAN_FAILED:
                $recipients = $recipients->merge(self::getScanInitiators($context));
                $recipients = $recipients->merge(self::getRepositoryOwners($context));
                $recipients = $recipients->merge(self::getUsersByRole(UserRole::ADMINISTRATOR->value));
                break;

            case NotificationType::REPORT_READY:
            case NotificationType::REPORT_FAILED:
                $recipients = $recipients->merge(self::getScanInitiators($context));
                $recipients = $recipients->merge(self::getRepositoryOwners($context));
                if ($type === NotificationType::REPORT_FAILED) {
                    $recipients = $recipients->merge(self::getUsersByRole(UserRole::ADMINISTRATOR->value));
                }
                break;

            case NotificationType::SYSTEM_ALERT:
                $recipients = $recipients->merge(self::getUsersByRole(UserRole::ADMINISTRATOR->value));
                break;
                
            // Note: CRITICAL_FINDING and HIGH_RISK_FINDING do not exist in NotificationType enum yet.
            // When added, they would route to repository owner, scan initiator, administrators, etc.
        }

        // Deduplicate by ID and filter out inactive/disabled users.
        return $recipients
            ->filter()
            ->unique('id')
            ->filter(function ($user) {
                // Ensure the user has an active status. Assumes UserStatus enum or string 'active'.
                // The User model casts status to UserStatus::class.
                $status = is_object($user->status) ? $user->status->value : $user->status;
                return strtolower($status) === 'active';
            })
            ->values();
    }

    protected static function getScanInitiators(NotificationContext $context): Collection
    {
        $initiators = collect();

        if ($context->initiator) {
            $initiators->push($context->initiator);
        } elseif ($context->scan && $context->scan->creator) {
            $initiators->push($context->scan->creator);
        } elseif ($context->report && $context->report->scan && $context->report->scan->creator) {
            $initiators->push($context->report->scan->creator);
        }

        return $initiators;
    }

    protected static function getRepositoryOwners(NotificationContext $context): Collection
    {
        $owners = collect();

        if ($context->repository && $context->repository->creator) {
            $owners->push($context->repository->creator);
        } elseif ($context->scan && $context->scan->repository && $context->scan->repository->creator) {
            $owners->push($context->scan->repository->creator);
        }

        return $owners;
    }

    protected static function getUsersByRole(string $roleSlug): Collection
    {
        return User::whereHas('role', function ($query) use ($roleSlug) {
            $query->where('slug', $roleSlug);
        })->get();
    }
}
