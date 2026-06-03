<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Normalize stored other-memo attachment rows and extract uploads from multipart requests.
 */
class OtherMemoAttachments
{
    /**
     * @param  mixed  $raw
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeStored(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $path = trim((string) ($row['path'] ?? $row['file_path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $rows[] = $row;
        }

        return array_values($rows);
    }

    /**
     * @return array<int, UploadedFile>
     */
    public static function extractUploadedFiles(Request $request): array
    {
        $files = $request->file('attachments');
        if ($files === null) {
            return [];
        }

        $out = [];

        if ($files instanceof UploadedFile) {
            if ($files->isValid()) {
                $out[0] = $files;
            }

            return $out;
        }

        if (! is_array($files)) {
            return [];
        }

        foreach ($files as $index => $item) {
            if ($item instanceof UploadedFile) {
                if ($item->isValid()) {
                    $out[(int) $index] = $item;
                }
                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            $file = $item['file'] ?? null;
            if ($file instanceof UploadedFile && $file->isValid()) {
                $out[(int) $index] = $file;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function collectFromCreateRequest(Request $request, bool $attachmentsEnabled): array
    {
        if (! $attachmentsEnabled) {
            return [];
        }

        $types = $request->input('attachments', []);
        if (! is_array($types)) {
            $types = [];
        }

        $out = [];
        foreach (self::extractUploadedFiles($request) as $index => $file) {
            $type = 'Document';
            if (isset($types[$index]) && is_array($types[$index])) {
                $t = $types[$index]['type'] ?? null;
                if (is_string($t) && trim($t) !== '') {
                    $type = trim($t);
                }
            }
            $out[] = array_merge(self::fileMetaFromUpload($file), ['type' => $type]);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function fileMetaFromUpload(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = time().'_'.uniqid('', true).'.'.$extension;
        $path = $file->storeAs('uploads/other-memos', $filename, 'public');

        return [
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Resolve stored path to a readable file on disk (public disk).
     */
    public static function resolveFilePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        $path = preg_replace('#^/+#', '', $path);
        $path = preg_replace('#^storage/+#', '', $path);

        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            return $disk->path($path);
        }

        $basePath = realpath(storage_path('app/public')) ?: storage_path('app/public');
        $fullPath = realpath($basePath.'/'.$path);
        if ($fullPath !== false && str_starts_with($fullPath, $basePath) && is_file($fullPath)) {
            return $fullPath;
        }

        return null;
    }
}
