<?php

namespace App\Http\Controllers;

use App\Support\StaffPhoto;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StaffMediaController extends Controller
{
    public function photo(string $filename): BinaryFileResponse
    {
        $path = StaffPhoto::resolveExistingPath($filename);
        if ($path === null) {
            abort(404);
        }

        return response()->file($path);
    }
}
