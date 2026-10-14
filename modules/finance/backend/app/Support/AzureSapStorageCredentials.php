<?php

namespace App\Support;

use App\Services\AzureBlobStorageClient;
use Staff\Shared\ModuleSettingsBag;
use Throwable;

/**
 * Resolve Azure Blob credentials for SAP dumps: DB (fin_settings) → env/config.
 */
final class AzureSapStorageCredentials
{
    public const KEY_CONNECTION_STRING = 'azure_sap_connection_string';

    public const KEY_ACCOUNT_NAME = 'azure_sap_account_name';

    public const KEY_ACCOUNT_KEY = 'azure_sap_account_key';

    public const KEY_CONTAINER = 'azure_sap_container';

    public const KEY_PREFIX = 'azure_sap_prefix';

    /**
     * @return array{
     *   connection_string: string,
     *   account_name: string,
     *   account_key: string,
     *   container: string,
     *   prefix: string,
     *   endpoint_suffix: string,
     *   timeout: int,
     *   sources: array{
     *     connection_string: string,
     *     account_name: string,
     *     account_key: string,
     *     container: string,
     *     prefix: string
     *   }
     * }
     */
    public static function resolve(?ModuleSettingsBag $bag = null): array
    {
        $bag ??= new RrSettingsBag;
        $cfg = static function (string $key, string $default = ''): string {
            $v = config('azure_sap.'.$key, $default);
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }

            return $default;
        };

        $pick = static function (string $dbKey, string $cfgKey, string $default = '') use ($bag, $cfg): array {
            if ($bag->has($dbKey)) {
                return ['value' => trim((string) $bag->get($dbKey)), 'source' => 'db'];
            }
            $v = $cfg($cfgKey, $default);
            if ($v !== '') {
                return ['value' => $v, 'source' => 'env'];
            }

            return ['value' => $default, 'source' => $default !== '' ? 'default' : 'unset'];
        };

        $conn = $pick(self::KEY_CONNECTION_STRING, 'connection_string');
        $account = $pick(self::KEY_ACCOUNT_NAME, 'account_name');
        $key = $pick(self::KEY_ACCOUNT_KEY, 'account_key');
        $container = $pick(self::KEY_CONTAINER, 'container', 'sap');
        $prefix = $pick(self::KEY_PREFIX, 'prefix', '');

        $parsed = self::parseConnectionString((string) $conn['value']);
        if ($account['value'] === '' && $parsed['account_name'] !== '') {
            $account = ['value' => $parsed['account_name'], 'source' => $conn['source']];
        }
        if ($key['value'] === '' && $parsed['account_key'] !== '') {
            $key = ['value' => $parsed['account_key'], 'source' => $conn['source']];
        }
        $endpointSuffix = $parsed['endpoint_suffix'] !== ''
            ? $parsed['endpoint_suffix']
            : $cfg('endpoint_suffix', 'core.windows.net');

        return [
            'connection_string' => (string) $conn['value'],
            'account_name' => (string) $account['value'],
            'account_key' => (string) $key['value'],
            'container' => (string) ($container['value'] !== '' ? $container['value'] : 'sap'),
            'prefix' => (string) $prefix['value'],
            'endpoint_suffix' => $endpointSuffix,
            'timeout' => (int) config('azure_sap.timeout', 60),
            'sources' => [
                'connection_string' => (string) $conn['source'],
                'account_name' => (string) $account['source'],
                'account_key' => (string) $key['source'],
                'container' => (string) $container['source'],
                'prefix' => (string) $prefix['source'],
            ],
        ];
    }

    /**
     * @return array{account_name: string, account_key: string, endpoint_suffix: string}
     */
    public static function parseConnectionString(string $connectionString): array
    {
        $out = [
            'account_name' => '',
            'account_key' => '',
            'endpoint_suffix' => '',
        ];
        if (trim($connectionString) === '') {
            return $out;
        }
        foreach (explode(';', $connectionString) as $part) {
            $part = trim($part);
            if ($part === '' || ! str_contains($part, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $part, 2);
            $k = strtolower(trim($k));
            $v = trim($v);
            if ($k === 'accountname') {
                $out['account_name'] = $v;
            } elseif ($k === 'accountkey') {
                $out['account_key'] = $v;
            } elseif ($k === 'endpointsuffix') {
                $out['endpoint_suffix'] = $v;
            }
        }

        return $out;
    }

    /**
     * @return array{
     *   settings: array<string, string>,
     *   resolved: array{
     *     account_name: string,
     *     container: string,
     *     prefix: string,
     *     account_key_configured: bool,
     *     connection_string_configured: bool,
     *     blob_endpoint: string
     *   },
     *   sources: array<string, string>
     * }
     */
    public static function snapshot(?ModuleSettingsBag $bag = null): array
    {
        $bag ??= new RrSettingsBag;
        $resolved = self::resolve($bag);
        $client = new AzureBlobStorageClient($resolved);

        return [
            'settings' => [
                'azure_sap_connection_string' => '',
                'azure_sap_account_name' => $bag->has(self::KEY_ACCOUNT_NAME)
                    ? trim((string) $bag->get(self::KEY_ACCOUNT_NAME))
                    : '',
                'azure_sap_account_key' => '',
                'azure_sap_container' => $bag->has(self::KEY_CONTAINER)
                    ? trim((string) $bag->get(self::KEY_CONTAINER))
                    : '',
                'azure_sap_prefix' => $bag->has(self::KEY_PREFIX)
                    ? trim((string) $bag->get(self::KEY_PREFIX))
                    : '',
            ],
            'resolved' => [
                'account_name' => $resolved['account_name'],
                'container' => $resolved['container'],
                'prefix' => $resolved['prefix'],
                'account_key_configured' => $resolved['account_key'] !== '',
                'connection_string_configured' => $resolved['connection_string'] !== '',
                'blob_endpoint' => $client->blobEndpoint(),
            ],
            'sources' => $resolved['sources'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function persist(ModuleSettingsBag $bag, array $input): string
    {
        if (array_key_exists('azure_sap_account_name', $input)) {
            $v = trim((string) ($input['azure_sap_account_name'] ?? ''));
            $bag->set(self::KEY_ACCOUNT_NAME, $v === '' ? null : $v);
        }
        if (array_key_exists('azure_sap_container', $input)) {
            $v = trim((string) ($input['azure_sap_container'] ?? ''));
            $bag->set(self::KEY_CONTAINER, $v === '' ? null : $v);
        }
        if (array_key_exists('azure_sap_prefix', $input)) {
            $v = trim((string) ($input['azure_sap_prefix'] ?? ''));
            $bag->set(self::KEY_PREFIX, $v === '' ? null : $v);
        }
        if (! empty($input['clear_connection_string'])) {
            $bag->set(self::KEY_CONNECTION_STRING, null);
        } elseif (array_key_exists('azure_sap_connection_string', $input)) {
            $cs = trim((string) ($input['azure_sap_connection_string'] ?? ''));
            if ($cs !== '') {
                $bag->set(self::KEY_CONNECTION_STRING, $cs);
            }
        }
        if (! empty($input['clear_account_key'])) {
            $bag->set(self::KEY_ACCOUNT_KEY, null);
        } elseif (array_key_exists('azure_sap_account_key', $input)) {
            $key = (string) ($input['azure_sap_account_key'] ?? '');
            if ($key !== '') {
                $bag->set(self::KEY_ACCOUNT_KEY, $key);
            }
        }

        return 'Azure SAP storage settings saved. Non-empty DB values override env.';
    }

    /**
     * @param  array{
     *   connection_string?: string,
     *   account_name?: string,
     *   account_key?: string,
     *   container?: string,
     *   prefix?: string
     * }|null  $override
     * @return array{success: bool, message: string, details: list<string>, files?: list<array<string, mixed>>}
     */
    public static function probe(?ModuleSettingsBag $bag = null, ?array $override = null): array
    {
        $bag ??= new RrSettingsBag;
        $resolved = self::resolve($bag);
        if (is_array($override)) {
            foreach (['connection_string', 'account_name', 'account_key', 'container', 'prefix'] as $k) {
                if (isset($override[$k]) && trim((string) $override[$k]) !== '') {
                    $resolved[$k] = trim((string) $override[$k]);
                }
            }
            if (! empty($override['connection_string'])) {
                $parsed = self::parseConnectionString((string) $override['connection_string']);
                if ($parsed['account_name'] !== '') {
                    $resolved['account_name'] = $parsed['account_name'];
                }
                if ($parsed['account_key'] !== '') {
                    $resolved['account_key'] = $parsed['account_key'];
                }
                if ($parsed['endpoint_suffix'] !== '') {
                    $resolved['endpoint_suffix'] = $parsed['endpoint_suffix'];
                }
            }
        }

        $details = [];
        if ($resolved['account_name'] === '' || $resolved['account_key'] === '') {
            return [
                'success' => false,
                'message' => 'FAILED — set connection string or account name + key.',
                'details' => ['missing credentials'],
            ];
        }
        $details[] = 'account='.$resolved['account_name'];
        $details[] = 'container='.$resolved['container'];
        if ($resolved['prefix'] !== '') {
            $details[] = 'prefix='.$resolved['prefix'];
        }

        try {
            $client = new AzureBlobStorageClient($resolved);
            $files = $client->listBlobs($resolved['prefix'] !== '' ? $resolved['prefix'] : null);
            $details[] = 'listed '.count($files).' blob(s)';
            $sample = array_slice(array_map(static fn ($f) => $f['name'], $files), 0, 5);
            foreach ($sample as $name) {
                $details[] = '  · '.$name;
            }

            return [
                'success' => true,
                'message' => 'OK — connected to Azure Blob container "'.$resolved['container'].'" ('.count($files).' file(s)).',
                'details' => $details,
                'files' => $files,
            ];
        } catch (Throwable $e) {
            $details[] = $e->getMessage();

            return [
                'success' => false,
                'message' => 'FAILED — '.$e->getMessage(),
                'details' => $details,
            ];
        }
    }
}
