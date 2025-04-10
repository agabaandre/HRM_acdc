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
                file_put_contents($cache_file, json_encode($data));
            }

            return $data;
        }
    }
}
