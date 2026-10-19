<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\EmailDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreEmailProviderRequest;
use App\Http\Requests\Api\V1\Admin\TestEmailProviderRequest;
use App\Http\Requests\Api\V1\Admin\UpdateEmailProviderRequest;
use App\Models\EmailProvider;
use App\Services\EmailDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmailProviderController extends Controller
{
    public function index(): JsonResponse
    {
        $providers = EmailProvider::query()
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->orderBy('name')
            ->get()
            ->map(function (EmailProvider $provider) {
                try {
                    return $this->transform($provider);
                } catch (\Throwable $e) {
                    report($e);

                    // Never fail the whole list because one row's secrets won't decrypt
                    return [
                        'id' => $provider->id,
                        'name' => $provider->name,
                        'slug' => $provider->slug,
                        'driver' => $provider->driver instanceof EmailDriver
                            ? $provider->driver->value
                            : (string) $provider->getRawOriginal('driver'),
                        'driver_label' => $provider->driver instanceof EmailDriver
                            ? $provider->driver->label()
                            : (string) $provider->getRawOriginal('driver'),
                        'config' => [],
                        'config_secrets' => [
                            'client_secret' => false,
                            'password' => false,
                            'secret' => false,
                        ],
                        'config_corrupt' => true,
                        'from_address' => $provider->from_address,
                        'from_name' => $provider->from_name,
                        'is_default' => (bool) $provider->is_default,
                        'is_active' => (bool) $provider->is_active,
                        'priority' => (int) $provider->priority,
                        'description' => $provider->description,
                        'created_at' => $provider->created_at,
                        'updated_at' => $provider->updated_at,
                    ];
                }
            })
            ->values();

        return response()->json(['data' => $providers]);
    }

    public function drivers(): JsonResponse
    {
        $drivers = collect(EmailDriver::cases())->map(fn (EmailDriver $driver) => [
            'value' => $driver->value,
            'label' => $driver->label(),
            'fields' => $this->driverFields($driver),
        ]);

        return response()->json(['data' => $drivers]);
    }

    public function store(StoreEmailProviderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name']);
        $data['config'] = $data['config'] ?? [];

        if (! empty($data['is_default'])) {
            EmailProvider::query()->update(['is_default' => false]);
        }

        try {
            $provider = EmailProvider::query()->create($data);
        } catch (\Illuminate\Encryption\MissingAppKeyException $e) {
            report($e);

            return response()->json([
                'message' => 'Server misconfiguration: APP_KEY is missing. Providers store encrypted credentials and cannot be saved until APP_KEY is set in backend/.env, then recreate the app container.',
            ], 500);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'No application encryption key')) {
                report($e);

                return response()->json([
                    'message' => 'Server misconfiguration: APP_KEY is missing. Run deploy/fix-app-key.sh on the server.',
                ], 500);
            }
            throw $e;
        }

        return response()->json(['data' => $this->transform($provider)], 201);
    }

    public function show(EmailProvider $emailProvider): JsonResponse
    {
        return response()->json(['data' => $this->transform($emailProvider)]);
    }

    public function update(UpdateEmailProviderRequest $request, EmailProvider $emailProvider): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('is_active', $data) && $data['is_active'] === false && $emailProvider->is_default) {
            return response()->json([
                'message' => 'Cannot disable the default provider. Set another provider as default first.',
            ], 422);
        }

        if (! empty($data['is_default'])) {
            EmailProvider::query()->where('id', '!=', $emailProvider->id)->update(['is_default' => false]);
        }

        $configIncoming = null;
        if (isset($data['config']) && is_array($data['config'])) {
            // Never overwrite stored secrets with blanks or UI placeholders.
            $configIncoming = $this->stripUnsetSecrets($data['config']);
            unset($data['config']);
        }

        try {
            if ($configIncoming !== null) {
                $emailProvider->setConfigSafely(array_merge($emailProvider->safeConfig(), $configIncoming));
            }
            $emailProvider->fill($data);
            $emailProvider->save();
        } catch (\Illuminate\Encryption\MissingAppKeyException $e) {
            report($e);

            return response()->json([
                'message' => 'Server misconfiguration: APP_KEY is missing. Providers store encrypted credentials and cannot be saved until APP_KEY is set in backend/.env, then recreate the app container.',
            ], 500);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'No application encryption key')) {
                report($e);

                return response()->json([
                    'message' => 'Server misconfiguration: APP_KEY is missing. Run deploy/fix-app-key.sh on the server.',
                ], 500);
            }
            throw $e;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            report($e);

            return response()->json([
                'message' => 'Stored credentials cannot be decrypted (APP_KEY may have changed). Re-enter the password/secret fields and save again.',
            ], 422);
        }

        return response()->json(['data' => $this->transform($emailProvider->fresh())]);
    }

    public function test(TestEmailProviderRequest $request, EmailProvider $emailProvider, EmailDispatchService $dispatch): JsonResponse
    {
        try {
            $log = $dispatch->testProvider($emailProvider, $request->validated('to'), $request->ip());
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'SMTP test failed. Check host, port, encryption, username, and password.',
            ], 422);
        }

        return response()->json([
            'message' => 'Test email sent.',
            'log' => $log,
        ]);
    }

    public function setDefault(EmailProvider $emailProvider): JsonResponse
    {
        EmailProvider::query()->update(['is_default' => false]);
        $emailProvider->update(['is_default' => true, 'is_active' => true]);

        return response()->json(['data' => $this->transform($emailProvider->fresh())]);
    }

    /** @var list<string> */
    private const SECRET_CONFIG_KEYS = ['client_secret', 'password', 'secret'];

    /**
     * @return array<string, mixed>
     */
    private function transform(EmailProvider $provider): array
    {
        $readable = $provider->configIsReadable();
        $config = $provider->safeConfig();
        $secretFlags = [];

        foreach (self::SECRET_CONFIG_KEYS as $key) {
            $secretFlags[$key] = $readable && ! empty($config[$key]);
            unset($config[$key]);
        }

        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'slug' => $provider->slug,
            'driver' => $provider->driver->value,
            'driver_label' => $provider->driver->label(),
            'config' => $config,
            'config_secrets' => $secretFlags,
            'config_corrupt' => ! $readable,
            'from_address' => $provider->from_address,
            'from_name' => $provider->from_name,
            'is_default' => $provider->is_default,
            'is_active' => $provider->is_active,
            'priority' => $provider->priority,
            'description' => $provider->description,
            'created_at' => $provider->created_at,
            'updated_at' => $provider->updated_at,
        ];
    }

    /**
     * Drop empty / placeholder secret values so updates keep the encrypted original.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function stripUnsetSecrets(array $config): array
    {
        $placeholders = ['********', '****', '••••••••', '[redacted]', 'redacted'];

        foreach ($config as $key => $value) {
            if ($value === null || $value === '') {
                unset($config[$key]);
                continue;
            }

            if (
                in_array($key, self::SECRET_CONFIG_KEYS, true)
                && is_string($value)
                && in_array(strtolower(trim($value)), $placeholders, true)
            ) {
                unset($config[$key]);
            }
        }

        return $config;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function driverFields(EmailDriver $driver): array
    {
        return match ($driver) {
            EmailDriver::Exchange => [
                ['key' => 'tenant_id', 'label' => 'Tenant ID', 'type' => 'text', 'required' => true],
                ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true],
                ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
                ['key' => 'redirect_uri', 'label' => 'Redirect URI', 'type' => 'text', 'required' => false],
                ['key' => 'scope', 'label' => 'Scope', 'type' => 'text', 'required' => false, 'default' => 'https://graph.microsoft.com/.default'],
                ['key' => 'auth_method', 'label' => 'Auth method', 'type' => 'select', 'required' => false, 'options' => [
                    ['value' => 'client_credentials', 'label' => 'Client credentials'],
                    ['value' => 'authorization_code', 'label' => 'Authorization code'],
                ]],
            ],
            EmailDriver::Smtp => [
                ['key' => 'host', 'label' => 'SMTP host', 'type' => 'text', 'required' => true],
                ['key' => 'port', 'label' => 'Port', 'type' => 'number', 'required' => true, 'default' => 587],
                ['key' => 'encryption', 'label' => 'Encryption', 'type' => 'select', 'required' => false, 'options' => [
                    ['value' => 'tls', 'label' => 'TLS'],
                    ['value' => 'ssl', 'label' => 'SSL'],
                    ['value' => '', 'label' => 'None'],
                ]],
                ['key' => 'username', 'label' => 'Username (mailbox email)', 'type' => 'text', 'required' => false],
                ['key' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => false],
            ],
            EmailDriver::Ses => [
                ['key' => 'key', 'label' => 'AWS access key', 'type' => 'text', 'required' => false],
                ['key' => 'secret', 'label' => 'AWS secret', 'type' => 'password', 'required' => false],
                ['key' => 'region', 'label' => 'Region', 'type' => 'text', 'required' => false, 'default' => 'us-east-1'],
            ],
            EmailDriver::Log => [],
        };
    }

    private function uniqueSlug(?string $slug, string $name): string
    {
        $base = Str::slug($slug ?: $name) ?: 'provider';
        $candidate = $base;
        $i = 1;
        while (EmailProvider::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }
}
