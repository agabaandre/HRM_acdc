<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Cache list data to file and retrieve if not expired or corrupted
 *
 * @param string $key - name of the file (without extension)
 * @param callable $callback - function to run if cache is expired, invalid or not found
 * @param int $ttl - time-to-live in seconds (default: 120)
 * @return mixed
 */
if (!function_exists('cache_list')) {
    function cache_list($key, callable $callback, $ttl = 120)
    {
        $CI =& get_instance();
        $cache_file = APPPATH . "cache/{$key}.json";
        $use_fresh = false;

        if (file_exists($cache_file)) {
            $file_age = time() - filemtime($cache_file);

            $contents = file_get_contents($cache_file);
            $data = json_decode($contents);

            if ($file_age < $ttl && !is_null($data)) {
                return $data;
            }

            // If file is expired or corrupted
            $use_fresh = true;
        } else {
            // File doesn't exist
            $use_fresh = true;
        }

        if ($use_fresh) {
            $data = call_user_func($callback);

            // Only cache if data is not empty
            if (!empty($data)) {
                cache_list_write($cache_file, $data);
            }

            return $data;
        }
    }
}

if (!function_exists('cache_list_write')) {
    /**
     * Write JSON cache file when application/cache is writable (avoids permission warnings).
     *
     * @param string $cache_file Absolute path to cache file
     * @param mixed  $data       Data to encode as JSON
     */
    function cache_list_write($cache_file, $data)
    {
        $cache_dir = dirname($cache_file);
        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0775, true);
        }

        $dir_writable = is_dir($cache_dir) && is_writable($cache_dir);
        $file_writable = file_exists($cache_file) && is_writable($cache_file);
        if (!$dir_writable && !$file_writable) {
            if (function_exists('log_message')) {
                log_message('error', 'Cache directory not writable: ' . $cache_dir . ' — run scripts/fix-ci-app-permissions.sh');
            }

            return false;
        }

        $written = @file_put_contents($cache_file, json_encode($data), LOCK_EX);
        if ($written === false && function_exists('log_message')) {
            log_message('error', 'Failed to write cache file: ' . $cache_file);
        }

        return $written !== false;
    }
}
