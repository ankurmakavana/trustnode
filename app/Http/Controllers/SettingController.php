<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TestEmailNotification;

class SettingController extends Controller
{
    /**
     * Get current SMTP settings (without exposing password).
     */
    public function getMailSettings(Request $request)
    {
        Gate::authorize('viewSettings', Setting::class);

        return response()->json([
            'success' => true,
            'data' => [
                'mail_mailer' => Setting::getValue('mail_mailer', config('mail.default')),
                'mail_host' => Setting::getValue('mail_host', config('mail.mailers.smtp.host')),
                'mail_port' => Setting::getValue('mail_port', config('mail.mailers.smtp.port')),
                'mail_username' => Setting::getValue('mail_username', config('mail.mailers.smtp.username')),
                'mail_encryption' => Setting::getValue('mail_encryption', config('mail.mailers.smtp.encryption', 'tls')),
                'mail_from_address' => Setting::getValue('mail_from_address', config('mail.from.address')),
                'mail_from_name' => Setting::getValue('mail_from_name', config('mail.from.name')),
            ]
        ]);
    }

    /**
     * Update SMTP settings securely.
     */
    public function updateMailSettings(Request $request)
    {
        Gate::authorize('updateSettings', Setting::class);

        $validated = $request->validate([
            'mail_mailer' => 'required|string',
            'mail_host' => 'required|string',
            'mail_port' => 'required|numeric',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string', // Optional to allow updating other fields without re-entering password
            'mail_encryption' => 'nullable|string',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string',
        ]);

        Setting::setValue('mail_mailer', $validated['mail_mailer']);
        Setting::setValue('mail_host', $validated['mail_host']);
        Setting::setValue('mail_port', $validated['mail_port']);
        Setting::setValue('mail_username', $validated['mail_username'] ?? '');
        Setting::setValue('mail_encryption', $validated['mail_encryption'] ?? '');
        Setting::setValue('mail_from_address', $validated['mail_from_address']);
        Setting::setValue('mail_from_name', $validated['mail_from_name']);

        if (!empty($validated['mail_password'])) {
            Setting::setValue('mail_password', $validated['mail_password'], true);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mail settings updated successfully.',
        ]);
    }

    /**
     * Send a test email.
     */
    public function testEmail(Request $request)
    {
        Gate::authorize('updateSettings', Setting::class);

        $validated = $request->validate([
            'to_email' => 'required|email',
        ]);

        // Temporarily set configuration to DB values for the test
        $this->applyMailSettings();

        try {
            Notification::route('mail', $validated['to_email'])
                ->notify(new TestEmailNotification());

            return response()->json([
                'success' => true,
                'message' => 'Test email queued successfully.',
            ]);
        } catch (\Exception $e) {
            // Log safe error without exposing credentials
            \Log::error('Test email failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email. Check server logs.',
            ], 500);
        }
    }

    /**
     * Danger Zone: Reset entire TrustNode application data.
     */
    public function resetPlatform(Request $request, \App\Services\ResetService $resetService)
    {
        Gate::authorize('updateSettings', Setting::class);

        $validated = $request->validate([
            'confirmation' => 'required|string',
            'create_backup' => 'nullable|boolean',
        ]);

        try {
            $user = $request->user();
            $result = $resetService->executeReset(
                adminUser: $user,
                confirmationPhrase: $validated['confirmation'],
                requireBackup: $validated['create_backup'] ?? true
            );

            // Invalidate current user session
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'backup' => $result['backup'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('Platform reset failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper to apply DB mail settings to current runtime config.
     */
    public static function applyMailSettings()
    {
        // Shared with the boot path, so every process applies the same installation-wide settings.
        \App\Services\Mail\MailSettingsApplier::apply();
    }
}
