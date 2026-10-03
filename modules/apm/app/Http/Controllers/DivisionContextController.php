<?php

namespace App\Http\Controllers;

use App\Support\StaffDivisionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DivisionContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'division_id' => ['required', 'integer', 'min:1'],
        ]);
        $ok = StaffDivisionContext::setActive((int) $validated['division_id']);
        if (! $ok) {
            return redirect()
                ->back()
                ->with(['msg' => 'You cannot switch to that division.', 'type' => 'error']);
        }

        $name = StaffDivisionContext::activeDivisionName() ?? 'selected division';

        return redirect()
            ->back()
            ->with(['msg' => 'Now acting as '.$name.'.', 'type' => 'success']);
    }
}
