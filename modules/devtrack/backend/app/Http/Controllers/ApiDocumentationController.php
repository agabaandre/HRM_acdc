<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;

class ApiDocumentationController extends Controller
{
    public function spec(): JsonResponse
    {
        $cached = storage_path('api-docs/openapi.json');
        $metaPath = storage_path('api-docs/openapi.meta.json');
        $openapiDir = app_path('OpenApi');
        $sourceHash = $this->openApiSourceHash($openapiDir);

        if (
            is_readable($cached)
            && is_readable($metaPath)
            && ! $this->openApiCacheIsStale($metaPath, $sourceHash)
        ) {
            try {
                /** @var array<string, mixed> $decoded */
                $decoded = json_decode((string) file_get_contents($cached), true, 512, JSON_THROW_ON_ERROR);

                return response()
                    ->json($this->withAppServer($decoded))
                    ->header('Cache-Control', 'no-store, private');
            } catch (Throwable) {
                // Fall through to live generation
            }
        }

        try {
            if (! class_exists(\OpenApi\Generator::class)) {
                return response()->json([
                    'message' => 'OpenAPI package missing. Run composer install in the app container.',
                    'error' => 'Class OpenApi\\Generator not found',
                ], 500);
            }

            $openapi = \OpenApi\Generator::scan([
                $openapiDir,
            ]);

            $json = $openapi->toJson();
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            @mkdir(dirname($cached), 0775, true);
            @file_put_contents($cached, $json);
            @file_put_contents($metaPath, json_encode([
                'source_hash' => $sourceHash,
                'generated_at' => now()->toIso8601String(),
            ], JSON_THROW_ON_ERROR));

            return response()
                ->json($this->withAppServer($decoded))
                ->header('Cache-Control', 'no-store, private');
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'OpenAPI generation failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function ui(): Response
    {
        try {
            $path = resource_path('swagger/ui.html');
            if (! is_readable($path)) {
                return response(
                    '<!DOCTYPE html><html><body><h1>API docs UI missing</h1><p>Expected file: resources/swagger/ui.html</p></body></html>',
                    500
                )->header('Content-Type', 'text/html; charset=UTF-8');
            }

            return response((string) file_get_contents($path), 200)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        } catch (Throwable $e) {
            report($e);

            return response(
                '<!DOCTYPE html><html><body><h1>API docs error</h1><pre>'.e($e->getMessage()).'</pre></body></html>',
                500
            )->header('Content-Type', 'text/html; charset=UTF-8');
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function withAppServer(array $spec): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $spec['servers'] = [
            [
                'url' => $base.'/api/v1',
                'description' => 'API v1',
            ],
        ];

        return $spec;
    }

    private function openApiCacheIsStale(string $metaPath, string $sourceHash): bool
    {
        try {
            /** @var array{source_hash?: string} $meta */
            $meta = json_decode((string) file_get_contents($metaPath), true, 512, JSON_THROW_ON_ERROR);

            return ($meta['source_hash'] ?? '') !== $sourceHash;
        } catch (Throwable) {
            return true;
        }
    }

    private function openApiSourceHash(string $openapiDir): string
    {
        if (! is_dir($openapiDir)) {
            return 'missing';
        }

        $hashes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($openapiDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $hashes[$path] = hash_file('sha256', $path) ?: '';
        }

        ksort($hashes);

        return hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR));
    }
}
