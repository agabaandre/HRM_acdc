<?php

namespace App\Http\Controllers;

use App\Support\StaffContractFile;
use App\Support\StaffPhoto;
use Illuminate\Support\Facades\DB;
use Modules\Staff\Support\StaffAccess;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StaffUploadController extends Controller
{
    public function photo(string $filename): BinaryFileResponse
    {
        $path = StaffPhoto::uploadsPath($filename);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    public function contract(string $filename): BinaryFileResponse
    {
        $safe = basename(str_replace('\\', '/', $filename));
        if (! StaffContractFile::exists($safe)) {
            abort(404);
        }

        $ownerStaffId = (int) DB::table('staff_contracts')->where('file_name', $safe)->value('staff_id');
        if ($ownerStaffId < 1 || ! StaffAccess::canViewProfile($ownerStaffId)) {
            abort(403);
        }

        return StaffContractFile::download($safe);
    }
}
