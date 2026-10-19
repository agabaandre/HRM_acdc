<?php

namespace App\Services;

use App\Models\TwoFactorChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AdminTwoFactorService
{
    private const CHALLENGE_MINUTES = 10;

    private const EMAIL_CODE_MINUTES = 10;

    private const TOTP_SETUP_MINUTES = 10;

    public function __construct(
        private readonly EmailDispatchService $dispatch,
        private readonly Google2FA $google2fa,
    ) {}

    /**
     * @return array{requires_2fa: true, challenge_token: string, methods: list<string>, email: string}
     */
    public function beginLoginChallenge(User $user): array
    {
        $plainToken = Str::random(64);
        $methods = $user->enabledTwoFactorMethods();
        $emailCodeHash = null;

        if ($user->two_factor_email_enabled) {
            $plainCode = $this->generateEmailCode();
            $emailCodeHash = Hash::make($plainCode);
            $this->sendEmailCode($user, $plainCode);
        }

        TwoFactorChallenge::query()->create([
            'user_id' => $user->id,
            'token_hash' => Hash::make($plainToken),
            'methods' => $methods,
            'email_code_hash' => $emailCodeHash,
            'expires_at' => now()->addMinutes(self::CHALLENGE_MINUTES),
        ]);

        return [
            'requires_2fa' => true,
            'challenge_token' => $plainToken,
            'methods' => $methods,
            'email' => $this->maskEmail($user->email),
        ];
    }

    public function verifyLoginChallenge(string $challengeToken, string $method, string $code): User
    {
        $challenge = $this->findOpenChallenge($challengeToken);
        $user = $challenge->user;

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'challenge_token' => ['This account has been deactivated.'],
            ]);
        }

        if (! in_array($method, $challenge->methods ?? [], true)) {
            throw ValidationException::withMessages([
                'method' => ['This verification method is not available for your account.'],
            ]);
        }

        if ($method === 'email') {
            $this->verifyEmailCode($challenge, $code);
        } else {
            $this->verifyTotpCode($user, $code);
        }

        $challenge->forceFill(['consumed_at' => now()])->save();

        return $user;
    }

    public function resendEmailCode(string $challengeToken): void
    {
        $challenge = $this->findOpenChallenge($challengeToken);
        $user = $challenge->user;

        if (! $user->two_factor_email_enabled) {
            throw ValidationException::withMessages([
                'challenge_token' => ['Email verification is not enabled for this account.'],
            ]);
        }

        $plainCode = $this->generateEmailCode();

        $challenge->forceFill([
            'email_code_hash' => Hash::make($plainCode),
        ])->save();

        $this->sendEmailCode($user, $plainCode);
    }

    public function enableEmail(User $user, string $password): void
    {
        $this->assertPassword($user, $password);

        $user->forceFill(['two_factor_email_enabled' => true])->save();
    }

    public function disableEmail(User $user, string $password): void
    {
        $this->assertPassword($user, $password);

        $user->forceFill(['two_factor_email_enabled' => false])->save();
        $this->revokeTokensIfNoTwoFactor($user);
    }

    /**
     * @return array{secret: string, otpauth_url: string}
     */
    public function beginTotpSetup(User $user, ?string $password = null): array
    {
        if ($user->mustSetupTotp()) {
            // First-login enrollment: password already verified at sign-in.
        } else {
            $this->assertPassword($user, (string) $password);
        }

        $secret = $this->google2fa->generateSecretKey();
        $issuer = config('app.name', 'Email Server');
        $otpauthUrl = $this->google2fa->getQRCodeUrl($issuer, $user->email, $secret);

        Cache::put(
            $this->totpSetupCacheKey($user),
            $secret,
            now()->addMinutes(self::TOTP_SETUP_MINUTES),
        );

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
        ];
    }

    /**
     * @return array{recovery_codes: list<string>}
     */
    public function confirmTotpSetup(User $user, string $code): array
    {
        $secret = Cache::get($this->totpSetupCacheKey($user));

        if (! is_string($secret) || $secret === '') {
            throw ValidationException::withMessages([
                'code' => ['Authenticator setup has expired. Please start again.'],
            ]);
        }

        if (! $this->google2fa->verifyKey($secret, $code)) {
            throw ValidationException::withMessages([
                'code' => ['The authenticator code is invalid.'],
            ]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_totp_secret' => $secret,
            'two_factor_totp_recovery_codes' => $this->hashRecoveryCodes($recoveryCodes),
            'two_factor_totp_enabled' => true,
        ])->save();

        Cache::forget($this->totpSetupCacheKey($user));

        return ['recovery_codes' => $recoveryCodes];
    }

    public function disableTotp(User $user, string $password): void
    {
        if ($user->requiresTotp()) {
            throw ValidationException::withMessages([
                'password' => ['Authenticator app verification is required for this account and cannot be disabled.'],
            ]);
        }

        $this->assertPassword($user, $password);

        $user->forceFill([
            'two_factor_totp_enabled' => false,
            'two_factor_totp_secret' => null,
            'two_factor_totp_recovery_codes' => null,
        ])->save();

        Cache::forget($this->totpSetupCacheKey($user));
        $this->revokeTokensIfNoTwoFactor($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function status(User $user): array
    {
        return [
            'two_factor_email_enabled' => (bool) $user->two_factor_email_enabled,
            'two_factor_totp_enabled' => (bool) $user->two_factor_totp_enabled,
            'totp_required' => $user->requiresTotp(),
            'must_setup_totp' => $user->mustSetupTotp(),
            'has_recovery_codes' => is_array($user->two_factor_totp_recovery_codes)
                && count($user->two_factor_totp_recovery_codes) > 0,
        ];
    }

    private function findOpenChallenge(string $challengeToken): TwoFactorChallenge
    {
        $candidates = TwoFactorChallenge::query()
            ->with('user')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->limit(25)
            ->get();

        foreach ($candidates as $challenge) {
            if (Hash::check($challengeToken, $challenge->token_hash)) {
                return $challenge;
            }
        }

        throw ValidationException::withMessages([
            'challenge_token' => ['This verification session is invalid or has expired.'],
        ]);
    }

    private function verifyEmailCode(TwoFactorChallenge $challenge, string $code): void
    {
        if ($challenge->email_code_hash === null || ! Hash::check($code, $challenge->email_code_hash)) {
            throw ValidationException::withMessages([
                'code' => ['The email verification code is invalid.'],
            ]);
        }
    }

    private function verifyTotpCode(User $user, string $code): void
    {
        $normalized = preg_replace('/\s+/', '', $code) ?? $code;

        if ($this->consumeRecoveryCode($user, $normalized)) {
            return;
        }

        $secret = $user->two_factor_totp_secret;

        if ($secret === null || $secret === '' || ! $this->google2fa->verifyKey($secret, $normalized)) {
            throw ValidationException::withMessages([
                'code' => ['The authenticator code is invalid.'],
            ]);
        }
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $hashedCodes = $user->two_factor_totp_recovery_codes;

        if (! is_array($hashedCodes) || $hashedCodes === []) {
            return false;
        }

        foreach ($hashedCodes as $index => $hashedCode) {
            if (! Hash::check($code, (string) $hashedCode)) {
                continue;
            }

            unset($hashedCodes[$index]);
            $user->forceFill([
                'two_factor_totp_recovery_codes' => array_values($hashedCodes),
            ])->save();

            return true;
        }

        return false;
    }

    private function assertPassword(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }
    }

    private function revokeTokensIfNoTwoFactor(User $user): void
    {
        if (! $user->fresh()?->hasTwoFactorEnabled()) {
            $user->tokens()->delete();
        }
    }

    private function generateEmailCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(Str::random(4).'-'.Str::random(4));
        }

        return $codes;
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function hashRecoveryCodes(array $codes): array
    {
        return array_map(static fn (string $code): string => Hash::make($code), $codes);
    }

    private function sendEmailCode(User $user, string $code): void
    {
        $appName = config('app.name', 'Email Server');

        $this->dispatch->queue(
            to: $user->email,
            subject: "{$appName} — sign-in verification code",
            body: $this->emailCodeBody($appName, $code),
            isHtml: true,
            source: 'two_factor_email',
            senderIp: request()?->ip(),
        );
    }

    private function emailCodeBody(string $appName, string $code): string
    {
        $minutes = self::EMAIL_CODE_MINUTES;
        $safeApp = e($appName);
        $safeCode = e($code);

        return <<<HTML
<p>Your <strong>{$safeApp}</strong> sign-in verification code is:</p>
<p style="font-size: 28px; font-weight: bold; letter-spacing: 4px;">{$safeCode}</p>
<p>This code expires in {$minutes} minutes.</p>
<p>If you did not attempt to sign in, change your password and contact an administrator.</p>
HTML;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($local === '' || $domain === '') {
            return $email;
        }

        $visible = substr($local, 0, 1);
        $maskedLocal = strlen($local) > 1 ? $visible.str_repeat('*', min(4, strlen($local) - 1)) : $visible;

        return "{$maskedLocal}@{$domain}";
    }

    private function totpSetupCacheKey(User $user): string
    {
        return "totp_setup:{$user->id}";
    }
}
