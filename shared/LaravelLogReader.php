<?php

namespace Staff\Shared;

/**
 * Read Laravel daily (and legacy single) log files with level / date filters.
 *
 * Inspired by opcodesio/log-viewer (search, level filters, daily files) but kept
 * in-process so every CBP module can expose the same API inside its own settings UI
 * without installing a separate /log-viewer app.
 *
 * Daily files: storage/logs/laravel-YYYY-MM-DD.log
 * Single file: storage/logs/laravel.log (date "single" when no dailies exist)
 *
 * @see https://log-viewer.opcodes.io/docs/3.x
 * @see https://github.com/opcodesio/log-viewer
 */
final class LaravelLogReader
{
    public const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    /** Severity rank (lower = more severe). Used for min_level filters. */
    private const LEVEL_RANK = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7,
    ];

    /**
     * @return array{
     *   logs_path: string,
     *   channel: string,
     *   stack: string,
     *   log_level: string,
     *   dates: list<string>,
     *   files: list<array{date: string, file: string, size: int, size_human: string, modified_at: string|null}>,
     *   levels: list<string>,
     *   default_date: string,
     *   mode: 'daily'|'single'|'empty',
     *   package_note: string
     * }
     */
    public static function meta(?string $logsPath = null): array
    {
        $dir = self::logsDir($logsPath);
        $dates = self::listDates($dir);
        $mode = 'empty';
        if ($dates !== []) {
            $mode = in_array('single', $dates, true) && count($dates) === 1 ? 'single' : 'daily';
        }
        $default = $dates[0] ?? date('Y-m-d');

        return [
            'logs_path' => $dir,
            'channel' => self::envStr('LOG_CHANNEL', 'stack'),
            'stack' => self::envStr('LOG_STACK', 'daily'),
            'log_level' => self::envStr('LOG_LEVEL', 'debug'),
            'dates' => $dates,
            'files' => self::fileCatalog($dir, $dates),
            'levels' => self::LEVELS,
            'default_date' => $default,
            'mode' => $mode,
            'package_note' => 'Built-in reader (opcodesio/log-viewer–style filters). Full package UI available via composer require opcodesio/log-viewer if you want a standalone /log-viewer.',
        ];
    }

    /**
     * @return array{
     *   date: string,
     *   level: string,
     *   min_level: string,
     *   query: string,
     *   page: int,
     *   per_page: int,
     *   total: int,
     *   last_page: int,
     *   file: string|null,
     *   file_exists: bool,
     *   file_size: int,
     *   file_size_human: string,
     *   level_counts: array<string, int>,
     *   entries: list<array{timestamp: string, level: string, env: string, message: string, preview: string}>
     * }
     */
    public static function read(
        string $date,
        string $level = '',
        string $query = '',
        int $page = 1,
        int $perPage = 100,
        ?string $logsPath = null,
        string $minLevel = '',
    ): array {
        $dir = self::logsDir($logsPath);
        $date = self::normalizeDate($date, $dir);
        $level = strtolower(trim($level));
        if ($level !== '' && ! in_array($level, self::LEVELS, true)) {
            $level = '';
        }
        $minLevel = strtolower(trim($minLevel));
        if ($minLevel !== '' && ! isset(self::LEVEL_RANK[$minLevel])) {
            $minLevel = '';
        }
        $query = trim($query);
        $page = max(1, $page);
        $perPage = max(10, min(500, $perPage));

        $file = self::fileForDate($dir, $date);
        $exists = $file !== null && is_file($file) && is_readable($file);
        $size = $exists ? (int) filesize($file) : 0;
        $entries = $exists ? self::parseFile($file) : [];

        $levelCounts = array_fill_keys(self::LEVELS, 0);
        foreach ($entries as $e) {
            $lv = $e['level'];
            if (isset($levelCounts[$lv])) {
                $levelCounts[$lv]++;
            }
        }

        if ($level !== '') {
            $entries = array_values(array_filter(
                $entries,
                static fn (array $e): bool => $e['level'] === $level
            ));
        } elseif ($minLevel !== '') {
            $maxRank = self::LEVEL_RANK[$minLevel];
            $entries = array_values(array_filter(
                $entries,
                static function (array $e) use ($maxRank): bool {
                    $rank = self::LEVEL_RANK[$e['level']] ?? 99;

                    return $rank <= $maxRank;
                }
            ));
        }
        if ($query !== '') {
            $qLower = mb_strtolower($query);
            $entries = array_values(array_filter(
                $entries,
                static fn (array $e): bool => str_contains(mb_strtolower($e['message']), $qLower)
                    || str_contains(mb_strtolower($e['timestamp']), $qLower)
                    || str_contains(mb_strtolower($e['level']), $qLower)
            ));
        }

        // Newest first (opcodesio default)
        $entries = array_reverse($entries);
        $total = count($entries);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $slice = array_slice($entries, ($page - 1) * $perPage, $perPage);
        foreach ($slice as &$row) {
            $row['preview'] = self::preview($row['message'], 240);
        }
        unset($row);

        return [
            'date' => $date,
            'level' => $level,
            'min_level' => $minLevel,
            'query' => $query,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'file' => $file !== null ? basename($file) : null,
            'file_exists' => $exists,
            'file_size' => $size,
            'file_size_human' => self::humanSize($size),
            'level_counts' => $levelCounts,
            'entries' => $slice,
        ];
    }

    private static function envStr(string $key, string $default = ''): string
    {
        if (function_exists('env')) {
            $v = env($key, $default);
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }
        $g = getenv($key);

        return is_string($g) && trim($g) !== '' ? trim($g) : $default;
    }

    private static function logsDir(?string $logsPath): string
    {
        if (is_string($logsPath) && $logsPath !== '') {
            return rtrim($logsPath, '/');
        }
        if (function_exists('storage_path')) {
            return storage_path('logs');
        }

        return sys_get_temp_dir();
    }

    /**
     * @param  list<string>  $dates
     * @return list<array{date: string, file: string, size: int, size_human: string, modified_at: string|null}>
     */
    private static function fileCatalog(string $dir, array $dates): array
    {
        $out = [];
        foreach ($dates as $date) {
            $path = self::fileForDate($dir, $date);
            $exists = $path !== null && is_file($path);
            $size = $exists ? (int) filesize($path) : 0;
            $mtime = $exists ? date('Y-m-d H:i:s', (int) filemtime($path)) : null;
            $out[] = [
                'date' => $date,
                'file' => $path !== null ? basename($path) : 'laravel-'.$date.'.log',
                'size' => $size,
                'size_human' => self::humanSize($size),
                'modified_at' => $mtime,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function listDates(string $dir): array
    {
        $dates = [];
        if (is_dir($dir)) {
            foreach (glob($dir.'/laravel-*.log') ?: [] as $path) {
                if (preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log$/', $path, $m)) {
                    $dates[] = $m[1];
                }
            }
        }
        rsort($dates, SORT_STRING);

        $today = date('Y-m-d');
        if (! in_array($today, $dates, true) && is_dir($dir)) {
            array_unshift($dates, $today);
            $dates = array_values(array_unique($dates));
            rsort($dates, SORT_STRING);
        }

        if ($dates === [] && is_file($dir.'/laravel.log')) {
            return ['single'];
        }

        return $dates;
    }

    private static function normalizeDate(string $date, string $dir): string
    {
        $date = trim($date);
        if ($date === 'single') {
            return 'single';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        $dates = self::listDates($dir);

        return $dates[0] ?? date('Y-m-d');
    }

    private static function fileForDate(string $dir, string $date): ?string
    {
        if ($date === 'single') {
            $single = $dir.'/laravel.log';

            return is_file($single) ? $single : null;
        }
        $daily = $dir.'/laravel-'.$date.'.log';
        if (is_file($daily)) {
            return $daily;
        }
        $hasDailies = (glob($dir.'/laravel-*.log') ?: []) !== [];
        if (! $hasDailies && $date === date('Y-m-d')) {
            $single = $dir.'/laravel.log';
            if (is_file($single)) {
                return $single;
            }
        }

        return $daily;
    }

    /**
     * @return list<array{timestamp: string, level: string, env: string, message: string}>
     */
    private static function parseFile(string $file): array
    {
        $raw = @file($file, FILE_IGNORE_NEW_LINES);
        if ($raw === false) {
            return [];
        }

        $entries = [];
        $current = null;
        // Match Laravel + Monolog lines; allow microseconds and optional timezone.
        $pattern = '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:[.,]\d+)?(?:[+-]\d{2}:?\d{2})?)\]\s+([\w.-]+)\.(\w+):\s*(.*)$/';

        foreach ($raw as $line) {
            if (preg_match($pattern, $line, $m)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = [
                    'timestamp' => $m[1],
                    'env' => $m[2],
                    'level' => strtolower($m[3]),
                    'message' => $m[4],
                ];
            } elseif ($current !== null) {
                $current['message'] .= "\n".$line;
            }
        }
        if ($current !== null) {
            $entries[] = $current;
        }

        return $entries;
    }

    private static function preview(string $message, int $max): string
    {
        $one = preg_replace('/\s+/', ' ', trim($message)) ?? '';
        if (mb_strlen($one) <= $max) {
            return $one;
        }

        return mb_substr($one, 0, $max - 1).'…';
    }

    private static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 2).' MB';
    }
}
