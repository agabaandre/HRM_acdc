<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Settings\Models\PortalAiProvider;
use Modules\Settings\Support\PortalAiProvidersConfig;

class PortalAiProviderService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function driverDefinitions(): array
    {
        return PortalAiProvidersConfig::drivers();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function driverDefinition(string $key): ?array
    {
        return PortalAiProvidersConfig::driver($key);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $this->ensureDefaultOpenAi();

        return PortalAiProvider::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (PortalAiProvider $p) => $this->present($p))
            ->all();
    }

    public function findByUuid(string $uuid): PortalAiProvider
    {
        return PortalAiProvider::query()->where('uuid', $uuid)->firstOrFail();
    }

    public function defaultProvider(): ?PortalAiProvider
    {
        $this->ensureDefaultOpenAi();

        return PortalAiProvider::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first()
            ?? PortalAiProvider::query()->where('is_active', true)->orderBy('id')->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PortalAiProvider
    {
        $this->assertDriver((string) ($data['driver'] ?? ''));

        return DB::transaction(function () use ($data) {
            $preset = $this->driverDefinition((string) $data['driver']);
            $row = PortalAiProvider::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => trim((string) $data['name']),
                'slug' => $this->uniqueSlug((string) ($data['slug'] ?? $data['name'])),
                'driver' => (string) $data['driver'],
                'api_endpoint' => $this->endpointFrom($data, $preset),
                'model' => $this->modelFrom($data, $preset),
                'api_key' => $this->encryptIncomingKey($data['api_key'] ?? null),
                'description' => $data['description'] ?? null,
                'is_default' => (bool) ($data['is_default'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if ($row->is_default) {
                $this->clearOtherDefaults($row->id);
            }

            return $row->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PortalAiProvider $provider, array $data): PortalAiProvider
    {
        return DB::transaction(function () use ($provider, $data) {
            $preset = $this->driverDefinition((string) ($data['driver'] ?? $provider->driver));
            $payload = [
                'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : $provider->name,
                'api_endpoint' => array_key_exists('api_endpoint', $data)
                    ? $this->endpointFrom($data, $preset)
                    : $provider->api_endpoint,
                'model' => array_key_exists('model', $data)
                    ? $this->modelFrom($data, $preset)
                    : $provider->model,
                'description' => array_key_exists('description', $data) ? $data['description'] : $provider->description,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $provider->is_active,
                'is_default' => array_key_exists('is_default', $data) ? (bool) $data['is_default'] : $provider->is_default,
            ];

            $encrypted = $this->encryptIncomingKey($data['api_key'] ?? null);
            if ($encrypted !== null) {
                $payload['api_key'] = $encrypted;
            }

            $provider->update($payload);

            if ($provider->is_default) {
                $this->clearOtherDefaults($provider->id);
            }

            return $provider->fresh();
        });
    }

    public function delete(PortalAiProvider $provider): void
    {
        if ($provider->is_default) {
            throw ValidationException::withMessages([
                'provider' => 'Cannot delete the default AI provider. Set another default first.',
            ]);
        }
        $provider->delete();
    }

    public function setDefault(PortalAiProvider $provider): PortalAiProvider
    {
        return DB::transaction(function () use ($provider) {
            $provider->update(['is_default' => true, 'is_active' => true]);
            $this->clearOtherDefaults($provider->id);

            return $provider->fresh();
        });
    }

    public function decryptKey(?PortalAiProvider $provider): string
    {
        if ($provider === null) {
            return '';
        }

        $enc = (string) ($provider->api_key ?? '');
        if ($enc !== '') {
            try {
                $plain = Crypt::decryptString($enc);
                if (is_string($plain) && trim($plain) !== '') {
                    return trim($plain);
                }
            } catch (\Throwable) {
                // fall through to env
            }
        }

        $preset = $this->driverDefinition((string) $provider->driver);
        $envName = is_array($preset) ? (string) ($preset['env_key'] ?? '') : '';
        if ($envName === '') {
            return '';
        }

        return trim((string) env($envName, ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function present(PortalAiProvider $provider): array
    {
        return [
            'id' => $provider->id,
            'uuid' => $provider->uuid,
            'name' => $provider->name,
            'slug' => $provider->slug,
            'driver' => $provider->driver,
            'api_endpoint' => $provider->api_endpoint,
            'model' => $provider->model,
            'has_api_key' => $this->decryptKey($provider) !== '',
            'description' => $provider->description,
            'is_default' => $provider->is_default,
            'is_active' => $provider->is_active,
            'created_at' => $provider->created_at,
            'updated_at' => $provider->updated_at,
        ];
    }

    public function ensureDefaultOpenAi(): void
    {
        if (! $this->tableReady()) {
            return;
        }
        if (PortalAiProvider::query()->exists()) {
            return;
        }

        $preset = $this->driverDefinition('openai') ?? [
            'api_endpoint' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
        ];
        $plain = trim((string) env('OPENAI_API_KEY', ''));

        PortalAiProvider::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'OpenAI',
            'slug' => 'openai',
            'driver' => 'openai',
            'api_endpoint' => (string) ($preset['api_endpoint'] ?? 'https://api.openai.com/v1'),
            'model' => (string) ($preset['model'] ?? 'gpt-4o-mini'),
            'api_key' => $plain !== '' ? Crypt::encryptString($plain) : null,
            'description' => 'Default OpenAI chat provider.',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function tableReady(): bool
    {
        return Schema::hasTable('portal_ai_providers');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $preset
     */
    private function endpointFrom(array $data, ?array $preset): string
    {
        $value = trim((string) ($data['api_endpoint'] ?? ''));
        if ($value !== '') {
            return rtrim($value, '/');
        }

        return rtrim((string) ($preset['api_endpoint'] ?? ''), '/');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $preset
     */
    private function modelFrom(array $data, ?array $preset): string
    {
        $value = trim((string) ($data['model'] ?? ''));
        if ($value !== '') {
            return $value;
        }

        return (string) ($preset['model'] ?? '');
    }

    private function encryptIncomingKey(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '********') {
            return null;
        }

        return Crypt::encryptString($trimmed);
    }

    private function assertDriver(string $driver): void
    {
        if (! in_array($driver, PortalAiProvidersConfig::driverKeys(), true)) {
            throw ValidationException::withMessages(['driver' => 'Unsupported AI provider.']);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'ai-provider';
        $slug = $base;
        $i = 1;
        while (PortalAiProvider::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function clearOtherDefaults(int $keepId): void
    {
        PortalAiProvider::query()->where('id', '!=', $keepId)->update(['is_default' => false]);
    }
}
