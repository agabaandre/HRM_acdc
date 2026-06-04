<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CbpAssetController extends Controller
{
    public function serve(string $path): BinaryFileResponse|Response
    {
        $assetsRoot = realpath(base_path('../assets'));
        if ($assetsRoot === false) {
            abort(404);
        }

        $path = str_replace(['..', '\\'], ['', '/'], $path);
        $file = realpath($assetsRoot.DIRECTORY_SEPARATOR.$path);

        if ($file === false || ! str_starts_with($file, $assetsRoot) || ! is_file($file)) {
            abort(404);
        }

        return response()->file($file);
    }
}
