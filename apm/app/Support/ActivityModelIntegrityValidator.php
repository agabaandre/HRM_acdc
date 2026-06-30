<?php

declare(strict_types=1);

namespace App\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Validates that App\Models\Activity is declared exactly once under apm/app.
 * Used by deploy scripts and composer validate-activity-model (no Pest required).
 */
final class ActivityModelIntegrityValidator
{
    /**
     * @return list<string> Human-readable errors; empty when valid.
     */
    public static function errors(string $appRoot): array
    {
        $errors = [];
        $canonical = $appRoot.'/Models/Activity.php';

        if (! is_file($canonical)) {
            return ['Missing canonical file: '.$canonical];
        }

        $contents = file_get_contents($canonical);
        if ($contents === false) {
            return ['Cannot read canonical Activity.php'];
        }

        if (preg_match_all('/^class Activity\b/m', $contents) !== 1) {
            $errors[] = 'Activity.php must contain exactly one "class Activity" declaration';
        }

        $declarations = self::declarationPaths($appRoot);
        if (count($declarations) !== 1) {
            $errors[] = sprintf(
                'Expected exactly one app file declaring class Activity, found %d: %s',
                count($declarations),
                implode(', ', $declarations)
            );
        } elseif (realpath($declarations[0]) !== realpath($canonical)) {
            $errors[] = 'Activity declaration is not in the canonical Models/Activity.php';
        }

        $lower = $appRoot.'/Models/activity.php';
        if (is_file($lower) && is_file($canonical)) {
            $canonicalReal = realpath($canonical);
            $lowerReal = realpath($lower);
            if ($canonicalReal !== $lowerReal) {
                $canonicalInode = $canonicalReal ? @fileinode($canonicalReal) : false;
                $lowerInode = $lowerReal ? @fileinode($lowerReal) : false;
                if ($canonicalInode === false || $lowerInode === false || $canonicalInode !== $lowerInode) {
                    $errors[] = 'Duplicate case-variant file exists: app/Models/activity.php (remove it on Linux)';
                }
            }
        }

        return $errors;
    }

    public static function isValid(string $appRoot): bool
    {
        return self::errors($appRoot) === [];
    }

    /**
     * @return list<string>
     */
    public static function declarationPaths(string $appRoot): array
    {
        $declarations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents !== false && preg_match('/^class Activity\b/m', $contents)) {
                $declarations[] = $file->getPathname();
            }
        }

        sort($declarations);

        return $declarations;
    }
}
