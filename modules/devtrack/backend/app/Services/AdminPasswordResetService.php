<?php

namespace App\Services;

use App\Models\User;
use App\Support\TrustedFrontendUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminPasswordResetService
{
    public function __construct(
        private readonly EmailDispatchService $dispatch,
    ) {}

    public function sendResetLink(string $email): void
    {
        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if ($user === null) {
            return;
        }

        $plainToken = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ],
        );

        $resetUrl = $this->resetUrl($email, $plainToken);
        $appName = config('app.name', 'Email Server');
        $expiryMinutes = (int) config('auth.passwords.users.expire', 60);

        $this->dispatch->queue(
            to: $email,
            subject: "{$appName} — password reset",
            body: $this->resetEmailBody($appName, $resetUrl, $expiryMinutes),
            isHtml: true,
            source: 'password_reset',
            senderIp: request()?->ip(),
        );
    }

    public function resetPassword(string $email, string $token, string $password): void
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if ($record === null || ! Hash::check($token, $record->token)) {
            throw ValidationException::withMessages([
                'token' => ['This password reset link is invalid or has expired.'],
            ]);
        }

        $expiresAt = now()->subMinutes((int) config('auth.passwords.users.expire', 60));
        $createdAt = $record->created_at ? Carbon::parse($record->created_at) : null;

        if ($createdAt === null || $createdAt->lt($expiresAt)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            throw ValidationException::withMessages([
                'token' => ['This password reset link is invalid or has expired.'],
            ]);
        }

        $user = User::query()->where('email', $email)->where('is_active', true)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => ['This password reset link is invalid or has expired.'],
            ]);
        }

        $user->forceFill(['password' => $password])->save();
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $email)->delete();
    }

    private function resetUrl(string $email, string $token): string
    {
        $base = TrustedFrontendUrl::base();

        return $base.'/reset-password?'.http_build_query([
            'email' => $email,
            'token' => $token,
        ]);
    }

    private function resetEmailBody(string $appName, string $resetUrl, int $expiryMinutes): string
    {
        $safeUrl = e($resetUrl);
        $safeApp = e($appName);
        $safeHost = e((string) (parse_url($resetUrl, PHP_URL_HOST) ?: ''));

        return <<<HTML
<p>You requested a password reset for your <strong>{$safeApp}</strong> admin account.</p>
<p><a href="{$safeUrl}">Reset your password</a></p>
<p>This link opens only on <strong>{$safeHost}</strong>. If the address looks unfamiliar, ignore this email.</p>
<p>This link expires in {$expiryMinutes} minutes and can only be used once.</p>
<p>If you did not request this, you can ignore this email. Your password will not change.</p>
HTML;
    }
}
