<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use App\Support\DevTrackCatalog;
use Illuminate\Http\JsonResponse;

class DevTrackMetaController extends Controller
{
    public function options(): JsonResponse
    {
        return response()->json(['data' => DevTrackCatalog::options()]);
    }

    public function team(): JsonResponse
    {
        $users = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_admin'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
            ])
            ->values();

        return response()->json(['data' => $users]);
    }

    /**
     * Staff directory options for named project roles.
     */
    public function staff(): JsonResponse
    {
        $items = Staff::query()
            ->where('active', true)
            ->orderBy('fname')
            ->orderBy('lname')
            ->get()
            ->map(fn (Staff $staff) => [
                'id' => $staff->id,
                'staff_id' => $staff->staff_id,
                'name' => $staff->fullName(),
                'email' => $staff->work_email,
                'division_name' => $staff->division_name,
                'duty_station_name' => $staff->duty_station_name,
            ])
            ->sortBy(fn (array $row) => mb_strtolower((string) $row['name']), SORT_NATURAL)
            ->values();

        return response()->json(['data' => $items]);
    }
}
