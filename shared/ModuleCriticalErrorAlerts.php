<?php

namespace Staff\Shared;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Admin email alerts when critical / error-level exceptions are reported.
 *
 * Settings resolve: DB → env → defaults.
 * Env keys: CRITICAL_ALERT_ENABLED, CRITICAL_ALERT_EMAILS,
 * CRITICAL_ALERT_MIN_LEVEL, CRITICAL_ALERT_COOLDOWN_MINUTES.
 */
final class ModuleCriticalErrorAlerts
{
    public const KEY_ENABLED = 'critical_alert_enabled';

    public const KEY_EMAILS = 'critical_alert_emails';

    public const KEY_MIN_LEVEL = 'critical_alert_min_level';

    public const KEY_COOLDOWN = 'critical_alert_cooldown_minutes';

    public const MIN_LEVELS = ['emergency', 'critical', 'error'];

    private static bool $sending = false;

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(?ModuleSettingsBag $bag = null, string $moduleLabel = 'Module'): array
    {
        $r = self::resolve($bag);

        return [
            'module' => $moduleLabel,
            'min_levels' => self::MIN_LEVELS,
            'settings' => [
                'critical_alert_enabled' => $bag?->has(self::KEY_ENABLED) ? (string) $bag->get(self::KEY_ENABLED) : '',
                'critical_alert_emails' => $bag?->has(self::KEY_EMAILS) ? (string) $bag->get(self::KEY_EMAILS) : '',
                'critical_alert_min_level' => $bag?->has(self::KEY_MIN_LEVEL) ? (string) $bag->get(self::KEY_MIN_LEVEL) : '',
                'critical_alert_cooldown_minutes' => $bag?->has(self::KEY_COOLDOWN) ? (string) $bag->get(self::KEY_COOLDOWN) : '',
            ],
            'resolved' => [
                'enabled' => $r['enabled'],
                'emails' => $r['emails'],
                'emails_display' => implode(', ', $r['emails']),
                'min_level' => $r['min_level'],
                'cooldown_minutes' => $r['cooldown_minutes'],
            ],
            'sources' => $r['sources'],
            'note' => 'When enabled, unhandled exceptions at or above the min level email these admins (with cooldown to avoid floods). Uses the module email / Staff Portal mail path.',
        ];
    }

    /**
     * @return array{
     *   enabled: bool,
     *   emails: list<string>,
     *   min_level: string,
     *   cooldown_minutes: int,
     *   sources: array<string, string>
     * }
     */
    public static function resolve(?ModuleSettingsBag $bag = null): array
    {
        $pick = static function (string $dbKey, array $envKeys, string $default = '') use ($bag): array {
            if ($bag instanceof ModuleSettingsBag && $bag->has($dbKey)) {
                return ['value' => trim((string) $bag->get($dbKey)), 'source' => 'db'];
            }
            foreach ($envKeys as $ek) {
                $v = self::envStr($ek);
                if ($v !== '') {
                    return ['value' => $v, 'source' => 'env'];
                }
            }

            return ['value' => $default, 'source' => $default !== '' ? 'default' : 'unset'];
        };

        $enabled = $pick(self::KEY_ENABLED, ['CRITICAL_ALERT_ENABLED'], 'false');
        $emailsRaw = $pick(self::KEY_EMAILS, ['CRITICAL_ALERT_EMAILS', 'ADMIN_EMAIL', 'MAIL_ADMIN_ADDRESS']);
        $minLevel = $pick(self::KEY_MIN_LEVEL, ['CRITICAL_ALERT_MIN_LEVEL'], 'error');
        $cooldown = $pick(self::KEY_COOLDOWN, ['CRITICAL_ALERT_COOLDOWN_MINUTES'], '30');

        $level = strtolower((string) $minLevel['value']);
        if (! in_array($level, self::MIN_LEVELS, true)) {
            $level = 'error';
        }

        return [
            'enabled' => self::boolish((string) $enabled['value']),
            'emails' => self::parseEmails((string) $emailsRaw['value']),
            'min_level' => $level,
            'cooldown_minutes' => max(1, min(1440, (int) $cooldown['value'])),
            'sources' => [
                'enabled' => (string) $enabled['source'],
                'emails' => (string) $emailsRaw['source'],
                'min_level' => (string) $minLevel['source'],
                'cooldown_minutes' => (string) $cooldown['source'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function persist(ModuleSettingsBag $bag, array $input): string
    {
        if (array_key_exists('critical_alert_enabled', $input)) {
            $v = $input['critical_alert_enabled'];
            if (is_bool($v)) {
                $bag->set(self::KEY_ENABLED, $v ? 'true' : 'false');
            } else {
                $s = strtolower(trim((string) $v));
                if ($s === '') {
                    $bag->set(self::KEY_ENABLED, null);
                } else {
                    $bag->set(self::KEY_ENABLED, self::boolish($s) ? 'true' : 'false');
                }
            }
        }

        if (array_key_exists('critical_alert_emails', $input)) {
            $parsed = self::parseEmails((string) $input['critical_alert_emails']);
            $bag->set(self::KEY_EMAILS, $parsed === [] ? null : implode(', ', $parsed));
        }

        if (array_key_exists('critical_alert_min_level', $input)) {
            $level = strtolower(trim((string) $input['critical_alert_min_level']));
            $bag->set(self::KEY_MIN_LEVEL, in_array($level, self::MIN_LEVELS, true) ? $level : null);
        }

        if (array_key_exists('critical_alert_cooldown_minutes', $input)) {
            $raw = trim((string) $input['critical_alert_cooldown_minutes']);
            if ($raw === '') {
                $bag->set(self::KEY_COOLDOWN, null);
            } else {
                $bag->set(self::KEY_COOLDOWN, (string) max(1, min(1440, (int) $raw)));
            }
        }

        return 'Critical error alert settings saved.';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{message: string, pairs: array<string, string>}
     */
    public static function persistAndMirrorEnv(
        ModuleSettingsBag $bag,
        array $input,
        string $moduleEnvPath,
        ?string $rootEnvPath = null,
    ): array {
        $message = self::persist($bag, $input);
        $resolved = self::resolve($bag);
        $pairs = [
            'CRITICAL_ALERT_ENABLED' => $resolved['enabled'] ? 'true' : 'false',
            'CRITICAL_ALERT_EMAILS' => implode(',', $resolved['emails']),
            'CRITICAL_ALERT_MIN_LEVEL' => $resolved['min_level'],
            'CRITICAL_ALERT_COOLDOWN_MINUTES' => (string) $resolved['cooldown_minutes'],
        ];

        if (is_file($moduleEnvPath) && is_writable($moduleEnvPath)) {
            ModuleEmailSettings::upsertEnvKeys($moduleEnvPath, $pairs);
        }
        $root = $rootEnvPath ?? dirname($moduleEnvPath, 2).'/.env';
        if (! is_file($root)) {
            $alt = dirname($moduleEnvPath, 3).'/.env';
            if (is_file($alt)) {
                $root = $alt;
            }
        }
        if (is_file($root) && is_writable($root)) {
            ModuleEmailSettings::upsertEnvKeys($root, $pairs);
        }
        foreach ($pairs as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return ['message' => $message, 'pairs' => $pairs];
    }

    /**
     * Register Laravel exception reporter (call from bootstrap/app.php).
     *
     * @param  (callable(): (?ModuleSettingsBag))|null  $bagFactory
     */
    public static function register(Exceptions $exceptions, string $moduleLabel, ?callable $bagFactory = null): void
    {
        $exceptions->reportable(function (Throwable $e) use ($moduleLabel, $bagFactory): void {
            $bag = null;
            if ($bagFactory !== null) {
                try {
                    $bag = $bagFactory();
                } catch (Throwable) {
                    $bag = null;
                }
            }
            self::notifyThrowable($e, $moduleLabel, $bag instanceof ModuleSettingsBag ? $bag : null);
        });
    }

    public static function notifyThrowable(Throwable $e, string $moduleLabel, ?ModuleSettingsBag $bag = null): void
    {
        if (self::$sending || self::shouldSkip($e)) {
            return;
        }

        $resolved = self::resolve($bag);
        if (! $resolved['enabled'] || $resolved['emails'] === []) {
            return;
        }

        // Unhandled exceptions map to "error" severity unless marked otherwise.
        $level = 'error';
        if (! self::levelAtLeast($level, $resolved['min_level'])) {
            return;
        }

        $fingerprint = md5($moduleLabel.'|'.get_class($e).'|'.$e->getMessage().'|'.$e->getFile().':'.$e->getLine());
        if (! self::passCooldown($fingerprint, $resolved['cooldown_minutes'])) {
            return;
        }

        $subject = sprintf(
            '[%s] %s: %s',
            $moduleLabel,
            strtoupper($level),
            self::truncate($e->getMessage() !== '' ? $e->getMessage() : get_class($e), 120)
        );
        $body = self::htmlBody($moduleLabel, $level, $e);

        self::send($resolved['emails'], $subject, $body, $moduleLabel);
    }

    /**
     * Manual / test send (ignores enabled flag when $force is true).
     *
     * @return array{success: bool, message: string}
     */
    public static function sendTest(?ModuleSettingsBag $bag, string $moduleLabel, bool $force = true): array
    {
        $resolved = self::resolve($bag);
        if (! $force && ! $resolved['enabled']) {
            return ['success' => false, 'message' => 'Critical alerts are disabled.'];
        }
        if ($resolved['emails'] === []) {
            return ['success' => false, 'message' => 'No admin emails configured.'];
        }

        $subject = sprintf('[%s] Critical alert test', $moduleLabel);
        $body = '<p>This is a <strong>test</strong> critical-error alert from <code>'
            .htmlspecialchars($moduleLabel, ENT_QUOTES, 'UTF-8')
            .'</code>.</p><p>Recipients: <code>'
            .htmlspecialchars(implode(', ', $resolved['emails']), ENT_QUOTES, 'UTF-8')
            .'</code></p><p>Sent at '.htmlspecialchars(gmdate('Y-m-d H:i:s').' UTC', ENT_QUOTES, 'UTF-8').'</p>';

        try {
            self::send($resolved['emails'], $subject, $body, $moduleLabel);

            return ['success' => true, 'message' => 'Test alert sent to '.implode(', ', $resolved['emails']).'.'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $emails
     */
    private static function send(array $emails, string $subject, string $body, string $moduleLabel): void
    {
        self::$sending = true;
        try {
            if (class_exists(StaffPortalMailClient::class)) {
                StaffPortalMailClient::fromAppConfig()->send($emails, $subject, $body);

                return;
            }
            throw new \RuntimeException('StaffPortalMailClient unavailable; cannot send critical alert for '.$moduleLabel);
        } finally {
            self::$sending = false;
        }
    }

    private static function shouldSkip(Throwable $e): bool
    {
        if ($e instanceof ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof AuthorizationException
            || $e instanceof ModelNotFoundException) {
            return true;
        }
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return true;
        }

        return false;
    }

    private static function levelAtLeast(string $level, string $minLevel): bool
    {
        $rank = ['emergency' => 0, 'critical' => 1, 'error' => 2, 'warning' => 3, 'notice' => 4, 'info' => 5, 'debug' => 6];
        $l = $rank[strtolower($level)] ?? 99;
        $m = $rank[strtolower($minLevel)] ?? 2;

        return $l <= $m;
    }

    private static function passCooldown(string $fingerprint, int $minutes): bool
    {
        $dir = self::cooldownDir();
        if ($dir === null) {
            return true;
        }
        $file = $dir.'/'.$fingerprint.'.ttl';
        if (is_file($file)) {
            $mtime = (int) filemtime($file);
            if ($mtime > 0 && (time() - $mtime) < ($minutes * 60)) {
                return false;
            }
        }
        @file_put_contents($file, (string) time());

        return true;
    }

    private static function cooldownDir(): ?string
    {
        $base = null;
        if (function_exists('storage_path')) {
            $base = storage_path('framework/cache/critical-alerts');
        } else {
            $base = sys_get_temp_dir().'/cbp-critical-alerts';
        }
        if (! is_dir($base) && ! @mkdir($base, 0775, true) && ! is_dir($base)) {
            return null;
        }

        return $base;
    }

    private static function htmlBody(string $moduleLabel, string $level, Throwable $e): string
    {
        $url = '';
        if (function_exists('request')) {
            try {
                $url = (string) request()->fullUrl();
            } catch (Throwable) {
                $url = '';
            }
        }
        $trace = self::truncate($e->getTraceAsString(), 4000);

        return '<h2>Critical error — '.htmlspecialchars($moduleLabel, ENT_QUOTES, 'UTF-8').'</h2>'
            .'<p><strong>Level:</strong> '.htmlspecialchars(strtoupper($level), ENT_QUOTES, 'UTF-8').'</p>'
            .'<p><strong>Exception:</strong> <code>'.htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8').'</code></p>'
            .'<p><strong>Message:</strong> '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</p>'
            .'<p><strong>Location:</strong> <code>'.htmlspecialchars($e->getFile().':'.$e->getLine(), ENT_QUOTES, 'UTF-8').'</code></p>'
            .($url !== '' ? '<p><strong>URL:</strong> '.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'</p>' : '')
            .'<p><strong>Time (UTC):</strong> '.htmlspecialchars(gmdate('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8').'</p>'
            .'<pre style="white-space:pre-wrap;font-size:12px;background:#f6f8fa;padding:12px;border-radius:6px;">'
            .htmlspecialchars($trace, ENT_QUOTES, 'UTF-8')
            .'</pre>';
    }

    /**
     * @return list<string>
     */
    private static function parseEmails(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '' && filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }

    private static function truncate(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max) {
            return $s;
        }

        return mb_substr($s, 0, $max - 1).'…';
    }

    private static function envStr(string $key): string
    {
        if (function_exists('env')) {
            $v = env($key);
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }
        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    private static function boolish(string $v): bool
    {
        $v = strtolower(trim($v));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}
