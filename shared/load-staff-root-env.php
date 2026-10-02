<?php

/**
 * Apply Staff monorepo root `.env` shared keys into the process environment.
 *
 * Module apps keep APP_KEY, DB_*, and per-app redirect URIs in their own `.env`.
 * Microsoft Graph credentials used for both login and email (EXCHANGE_*), plus
 * JWT_SECRET / SESSION_SECRET / shared mail defaults, are owned by `/staff/.env`
 * and always win over stale copies in module envs.
 */
function staff_root_env_path(): string
{
    static $cached = null;
    if (is_string($cached)) {
        return $cached;
    }

    $dir = dirname(__DIR__);
    if (is_file($dir.'/.env') && is_dir($dir.'/modules')) {
        $cached = $dir.'/.env';

        return $cached;
    }

    $cached = '';

    return $cached;
}

/**
 * @return array<string, string>
 */
function staff_parse_env_file(string $path): array
{
    if (! is_readable($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return [];
    }

    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (! str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        if ($name === '' || ! preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            continue;
        }
        $value = trim($value);
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        $out[$name] = $value;
    }

    return $out;
}

function staff_env_put(string $name, string $value): void
{
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
    putenv($name.'='.$value);
}

/**
 * Shared keys owned by the staff root `.env` (login + email + SSO).
 *
 * @return list<string>
 */
function staff_shared_env_keys(): array
{
    return [
        // Single Azure app for Microsoft login + Graph/Exchange mail
        'EXCHANGE_TENANT_ID',
        'EXCHANGE_CLIENT_ID',
        'EXCHANGE_CLIENT_SECRET',
        'EXCHANGE_SCOPE',
        'EXCHANGE_AUTH_METHOD',
        // Aliases kept in sync for older MICROSOFT_* / TENANT_ID readers
        'MICROSOFT_TENANT_ID',
        'MICROSOFT_CLIENT_ID',
        'MICROSOFT_CLIENT_SECRET',
        'TENANT_ID',
        'CLIENT_ID',
        'CLIENT_SEC_VALUE',
        // SSO / session shared across CBP modules
        'JWT_SECRET',
        'SESSION_SECRET',
        // Shared outbound mail defaults (modules may still set MAIL_FROM_NAME)
        'MAIL_FROM_ADDRESS',
        'MAIL_MAILER',
        'MAIL_TRANSPORT',
        'USE_EXCHANGE_EMAIL',
        'MAIL_HTTP_BASE_URL',
        'MAIL_HTTP_CLIENT_ID',
        'MAIL_HTTP_CLIENT_SECRET',
        'STAFF_MAIL_CONFIG_KEY',
        'STAFF_MAIL_DISPATCH',
        'MAIL_HOST',
        'MAIL_PORT',
        'MAIL_USERNAME',
        'MAIL_PASSWORD',
        'MAIL_DRIVER',
    ];
}

function staff_load_root_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $rootEnv = staff_root_env_path();
    if ($rootEnv === '') {
        return;
    }

    $vars = staff_parse_env_file($rootEnv);
    if ($vars === []) {
        return;
    }

    // Canonical Graph credentials — EXCHANGE_* is the source of truth.
    $tenant = $vars['EXCHANGE_TENANT_ID'] ?? $vars['MICROSOFT_TENANT_ID'] ?? $vars['TENANT_ID'] ?? '';
    $clientId = $vars['EXCHANGE_CLIENT_ID'] ?? $vars['MICROSOFT_CLIENT_ID'] ?? $vars['CLIENT_ID'] ?? '';
    $clientSecret = $vars['EXCHANGE_CLIENT_SECRET']
        ?? $vars['MICROSOFT_CLIENT_SECRET']
        ?? $vars['CLIENT_SEC_VALUE']
        ?? '';
    $scope = $vars['EXCHANGE_SCOPE'] ?? 'https://graph.microsoft.com/.default';
    $authMethod = $vars['EXCHANGE_AUTH_METHOD'] ?? 'client_credentials';

    if ($tenant !== '') {
        $vars['EXCHANGE_TENANT_ID'] = $tenant;
        $vars['MICROSOFT_TENANT_ID'] = $tenant;
        $vars['TENANT_ID'] = $tenant;
    }
    if ($clientId !== '') {
        $vars['EXCHANGE_CLIENT_ID'] = $clientId;
        $vars['MICROSOFT_CLIENT_ID'] = $clientId;
        $vars['CLIENT_ID'] = $clientId;
    }
    if ($clientSecret !== '') {
        $vars['EXCHANGE_CLIENT_SECRET'] = $clientSecret;
        $vars['MICROSOFT_CLIENT_SECRET'] = $clientSecret;
        $vars['CLIENT_SEC_VALUE'] = $clientSecret;
    }
    $vars['EXCHANGE_SCOPE'] = $scope;
    $vars['EXCHANGE_AUTH_METHOD'] = $authMethod;

    foreach (staff_shared_env_keys() as $key) {
        if (! array_key_exists($key, $vars)) {
            continue;
        }
        $value = $vars[$key];
        if (! is_string($value) || trim($value) === '') {
            continue;
        }
        staff_env_put($key, $value);
    }
}

staff_load_root_env();
