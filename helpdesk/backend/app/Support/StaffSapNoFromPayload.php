<?php

namespace App\Support;

use Illuminate\Support\Str;

final class StaffSapNoFromPayload
{
    /**
     * When any known SAP key is present on the payload, return attributes to merge into profile update.
     * When no such key exists, return null so existing DB values are left unchanged.
     *
     * @param  array<string, mixed>  $payload
     * @return array{sap_no: string|null}|null
     */
    public static function attributeIfPresent(array $payload): ?array
    {
        foreach (['SAPNO', 'sap_no', 'sapno'] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $raw = trim((string) $payload[$key]);

            return ['sap_no' => $raw === '' ? null : Str::limit($raw, 64, '')];
        }

        return null;
    }
}
