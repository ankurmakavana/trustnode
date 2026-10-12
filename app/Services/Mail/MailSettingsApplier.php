<?php

namespace App\Services\Mail;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Applies saved mail settings to the runtime mail configuration in every process (web, queue worker,
 * scheduler). Settings are installation-wide, so they are read without a user. A key that has never been
 * saved keeps its .env default. Values are never logged.
 */
class MailSettingsApplier
{
    private const KEYS = [
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
    ];

    public static function apply(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $values = Setting::globalValues(self::KEYS);
        if ($values === []) {
            return;
        }

        if (isset($values['mail_mailer']) && $values['mail_mailer'] !== '') {
            Config::set('mail.default', $values['mail_mailer']);

            if ($values['mail_mailer'] === 'smtp') {
                self::setIfPresent($values, 'mail_host', 'mail.mailers.smtp.host');
                self::setIfPresent($values, 'mail_port', 'mail.mailers.smtp.port');
                self::setIfPresent($values, 'mail_username', 'mail.mailers.smtp.username');
                self::setIfPresent($values, 'mail_password', 'mail.mailers.smtp.password');
                self::setIfPresent($values, 'mail_encryption', 'mail.mailers.smtp.encryption');
            }
        }

        if (isset($values['mail_from_address']) && $values['mail_from_address'] !== '') {
            Config::set('mail.from.address', $values['mail_from_address']);
            self::setIfPresent($values, 'mail_from_name', 'mail.from.name');
        }
    }

    /**
     * Boot-time entry point. A failure must never stop the application from starting, so it is logged
     * without its details and the .env defaults stay in force.
     */
    public static function applyAtBoot(): void
    {
        try {
            self::apply();
        } catch (\Throwable $e) {
            Log::warning('Saved mail settings could not be applied; .env defaults are used.', [
                'exception' => $e::class,
            ]);
        }
    }

    private static function setIfPresent(array $values, string $key, string $configKey): void
    {
        if (array_key_exists($key, $values) && $values[$key] !== null) {
            Config::set($configKey, $values[$key]);
        }
    }
}
