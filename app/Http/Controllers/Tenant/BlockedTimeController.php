<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\BlockedTime;
use Illuminate\Http\Request;

class BlockedTimeController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'stylist_id' => ['nullable', 'uuid', 'exists:stylists,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
            'is_salon_wide' => ['boolean'],
        ]);

        BlockedTime::create([
            'stylist_id' => $data['is_salon_wide'] ?? false ? null : $data['stylist_id'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'reason' => $data['reason'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Bloqueo creado.');
    }

    public function destroy(BlockedTime $blockedTime)
    {
        $blockedTime->delete();

        return back()->with('success', 'Bloqueo eliminado.');
    }
}
