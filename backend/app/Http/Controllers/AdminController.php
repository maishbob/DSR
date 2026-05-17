<?php

namespace App\Http\Controllers;

use App\Models\Owner;
use App\Models\Station;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminController extends Controller
{
    // ── Owners ───────────────────────────────────────────────────

    public function owners(Request $request)
    {
        $owners = Owner::with(['user:id,name,email', 'stations:id,owner_id,station_name,location,is_active'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Owners', [
            'owners' => $owners,
        ]);
    }

    public function storeOwner(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:50',
            'password' => 'required|string|min:8',
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'role'     => 'owner',
            ]);

            $owner = Owner::create([
                'user_id' => $user->id,
                'name'    => $request->name,
                'email'   => $request->email,
                'phone'   => $request->phone,
                'is_active' => true,
            ]);

            // Set owner_id back on user
            $user->update(['owner_id' => $owner->id]);
        });

        return back()->with('success', "Owner '{$request->name}' created.");
    }

    public function updateOwner(Request $request, Owner $owner)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('owners', 'email')->ignore($owner->id)],
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $owner->update($request->only('name', 'email', 'phone', 'is_active'));

        // Sync user record
        if ($owner->user) {
            $owner->user->update([
                'name'  => $request->name,
                'email' => $request->email,
            ]);
        }

        return back()->with('success', "Owner '{$owner->name}' updated.");
    }

    public function resetPassword(Request $request, User $user)
    {
        abort_if($user->isSuperAdmin(), 403, 'Cannot reset a super admin password.');

        $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', "Password reset for '{$user->name}'.");
    }

    // ── Stations ─────────────────────────────────────────────────

    public function stations(Request $request)
    {
        $stations = Station::with('owner:id,name')
            ->withCount(['products', 'tanks', 'pumpNozzles', 'shifts', 'creditCustomers'])
            ->orderBy('station_name')
            ->get();

        $owners = Owner::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Stations', [
            'stations' => $stations,
            'owners'   => $owners,
        ]);
    }

    public function storeStation(Request $request)
    {
        $request->validate([
            'owner_id'     => 'required|exists:owners,id',
            'station_name' => 'required|string|max:255',
            'location'     => 'nullable|string|max:255',
        ]);

        Station::create([
            'owner_id'     => $request->owner_id,
            'station_name' => $request->station_name,
            'location'     => $request->location,
            'is_active'    => true,
        ]);

        return back()->with('success', "Station '{$request->station_name}' created.");
    }

    public function updateStation(Request $request, Station $station)
    {
        $request->validate([
            'owner_id'     => 'required|exists:owners,id',
            'station_name' => 'required|string|max:255',
            'location'     => 'nullable|string|max:255',
            'is_active'    => 'boolean',
        ]);

        $station->update($request->only('owner_id', 'station_name', 'location', 'is_active'));

        return back()->with('success', "Station '{$station->station_name}' updated.");
    }
}
