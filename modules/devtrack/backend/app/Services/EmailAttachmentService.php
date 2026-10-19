<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Store / load / cleanup email attachments for queued delivery.
 *
 * API shape (JSON):
 *   attachments: [{ filename, content (base64), content_type? }]
 */
class EmailAttachmentService
{
    public const DISK = 'local';

    public const STORAGE_PREFIX = 'email-attachments';

    /** @var list<string> */
    private const BLOCKED_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'cpl', 'scr', 'msi', 'dll',
        'js', 'jse', 'vbs', 'vbe', 'wsf', 'wsh', 'ps1', 'jar',
    ];

    public function maxCount(): int
    {
        return max(1, (int) config('services.mail_attachments.max_count', 10));
    }

    public function maxBytesPerFile(): int
    {
        return max(1024, (int) config('services.mail_attachments.max_bytes_per_file', 5 * 1024 * 1024));
    }

    public function maxBytesTotal(): int
    {
        return max(1024, (int) config('services.mail_attachments.max_bytes_total', 15 * 1024 * 1024));
    }

    /**
     * @return list<array{filename: string, content: string, content_type: string, size: int}>
     */
    public function normalizeFromRequest(?array $raw): array
    {
        if ($raw === null || $raw === []) {
            return [];
        }

        if (count($raw) > $this->maxCount()) {
            throw ValidationException::withMessages([
                'attachments' => ["You may attach at most {$this->maxCount()} files."],
            ]);
        }

        $normalized = [];
        $total = 0;

        foreach (array_values($raw) as $index => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    "attachments.{$index}" => ['Each attachment must be an object with filename and content.'],
                ]);
            }

            $filename = $this->sanitizeFilename((string) ($item['filename'] ?? $item['name'] ?? ''));
            if ($filename === '') {
                throw ValidationException::withMessages([
                    "attachments.{$index}.filename" => ['A valid filename is required.'],
                ]);
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($extension !== '' && in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
                throw ValidationException::withMessages([
                    "attachments.{$index}.filename" => ["File type .{$extension} is not allowed."],
                ]);
            }

            $contentB64 = (string) ($item['content'] ?? $item['content_base64'] ?? '');
            if ($contentB64 === '') {
                throw ValidationException::withMessages([
                    "attachments.{$index}.content" => ['Base64 content is required.'],
                ]);
            }

            // Strip data-URI prefix if present.
            if (str_contains($contentB64, ',')) {
                $contentB64 = substr($contentB64, strpos($contentB64, ',') + 1);
            }

            $binary = base64_decode(preg_replace('/\s+/', '', $contentB64) ?? '', true);
            if ($binary === false) {
                throw ValidationException::withMessages([
                    "attachments.{$index}.content" => ['Attachment content must be valid base64.'],
                ]);
            }

            $size = strlen($binary);
            if ($size === 0) {
                throw ValidationException::withMessages([
                    "attachments.{$index}.content" => ['Attachment content is empty.'],
                ]);
            }

            if ($size > $this->maxBytesPerFile()) {
                $mb = round($this->maxBytesPerFile() / 1024 / 1024, 1);
                throw ValidationException::withMessages([
                    "attachments.{$index}.content" => ["Each attachment must be at most {$mb} MB."],
                ]);
            }

            $total += $size;
            if ($total > $this->maxBytesTotal()) {
                $mb = round($this->maxBytesTotal() / 1024 / 1024, 1);
                throw ValidationException::withMessages([
                    'attachments' => ["Total attachment size must be at most {$mb} MB."],
                ]);
            }

            $contentType = trim((string) ($item['content_type'] ?? $item['mime_type'] ?? ''));
            if ($contentType === '' || ! preg_match('/^[a-z0-9!#$&^_.+-]+\/[a-z0-9!#$&^_.+-]+$/i', $contentType)) {
                $contentType = $this->guessContentType($filename);
            }

            $normalized[] = [
                'filename' => $filename,
                'content' => $binary,
                'content_type' => $contentType,
                'size' => $size,
            ];
        }

        return $normalized;
    }

    /**
     * Persist binaries to disk and return meta rows (no raw content).
     *
     * @param  list<array{filename: string, content: string, content_type: string, size: int}>  $attachments
     * @return list<array{filename: string, path: string, content_type: string, size: int}>
     */
    public function store(array $attachments, ?int $emailLogId = null): array
    {
        if ($attachments === []) {
            return [];
        }

        $dir = self::STORAGE_PREFIX.'/'.($emailLogId ?? Str::uuid()->toString());
        $stored = [];

        foreach ($attachments as $attachment) {
            $safeName = $this->uniqueStoredName($dir, $attachment['filename']);
            $path = $dir.'/'.$safeName;
            Storage::disk(self::DISK)->put($path, $attachment['content']);

            $stored[] = [
                'filename' => $attachment['filename'],
                'path' => $path,
                'content_type' => $attachment['content_type'],
                'size' => $attachment['size'],
            ];
        }

        return $stored;
    }

    /**
     * @param  list<array{filename?: string, path?: string, content_type?: string, size?: int}>  $metaAttachments
     * @return list<array{filename: string, content: string, content_type: string, size: int}>
     */
    public function load(array $metaAttachments): array
    {
        $loaded = [];

        foreach ($metaAttachments as $row) {
            $path = (string) ($row['path'] ?? '');
            if ($path === '' || ! Storage::disk(self::DISK)->exists($path)) {
                throw new RuntimeException('Queued attachment file is missing: '.($row['filename'] ?? $path));
            }

            $content = Storage::disk(self::DISK)->get($path);
            if (! is_string($content)) {
                throw new RuntimeException('Queued attachment could not be read: '.($row['filename'] ?? $path));
            }

            $loaded[] = [
                'filename' => (string) ($row['filename'] ?? basename($path)),
                'content' => $content,
                'content_type' => (string) ($row['content_type'] ?? 'application/octet-stream'),
                'size' => (int) ($row['size'] ?? strlen($content)),
            ];
        }

        return $loaded;
    }

    /**
     * @param  list<array{path?: string}>  $metaAttachments
     */
    public function deleteStored(array $metaAttachments): void
    {
        $dirs = [];

        foreach ($metaAttachments as $row) {
            $path = (string) ($row['path'] ?? '');
            if ($path === '') {
                continue;
            }

            Storage::disk(self::DISK)->delete($path);
            $dirs[dirname($path)] = true;
        }

        foreach (array_keys($dirs) as $dir) {
            if ($dir === '.' || $dir === self::STORAGE_PREFIX) {
                continue;
            }
            if (Storage::disk(self::DISK)->exists($dir) && Storage::disk(self::DISK)->files($dir) === []) {
                Storage::disk(self::DISK)->deleteDirectory($dir);
            }
        }
    }

    /**
     * Validation rules fragment for FormRequests.
     *
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return [
            'attachments' => ['sometimes', 'nullable', 'array', 'max:'.$this->maxCount()],
            'attachments.*.filename' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.name' => ['sometimes', 'string', 'max:255'],
            'attachments.*.content' => ['required_without:attachments.*.content_base64', 'string'],
            'attachments.*.content_base64' => ['sometimes', 'string'],
            'attachments.*.content_type' => ['sometimes', 'nullable', 'string', 'max:127'],
            'attachments.*.mime_type' => ['sometimes', 'nullable', 'string', 'max:127'],
        ];
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = str_replace(["\0", '\\'], '', $filename);
        $filename = basename(str_replace(['/', '\\'], '-', $filename));
        $filename = trim($filename);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return '';
        }

        // Avoid header/path oddities.
        $filename = preg_replace('/[\r\n\t"]+/', '', $filename) ?? '';

        return mb_substr($filename, 0, 255);
    }

    private function uniqueStoredName(string $dir, string $filename): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'attachment.bin';
        $candidate = $safe;
        $i = 1;
        while (Storage::disk(self::DISK)->exists($dir.'/'.$candidate)) {
            $candidate = pathinfo($safe, PATHINFO_FILENAME).'_'.$i.'.'.(pathinfo($safe, PATHINFO_EXTENSION) ?: 'bin');
            $i++;
        }

        return $candidate;
    }

    private function guessContentType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'html', 'htm' => 'text/html',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'zip' => 'application/zip',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/octet-stream',
        };
    }
}
