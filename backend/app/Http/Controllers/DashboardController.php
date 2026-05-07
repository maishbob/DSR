<?php

namespace App\Http\Controllers;

use App\Models\DailySalesRecord;
use App\Models\Delivery;
use App\Models\Shift;
use App\Models\Station;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function index(Request $request)
    {
        $user    = $request->user();

        // Super admins go to their admin area
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.owners');
        }

        $station = $this->resolveStation($user, $request);

        // Owner with no station selected → station picker
        if (! $station) {
            return redirect()->route('select-station');
        }

        $today   = now()->toDateString();
        $kpis    = $this->reportService->dashboardKpis($station, $today);

        return Inertia::render('Dashboard', array_merge([
            'station' => $station,
        ], $kpis));
    }

    public function ownerDashboard(Request $request)
    {
        $user  = $request->user();
        $owner = $user->owner ?? $user->ownedAccount;
        $from  = now()->subDays(30)->toDateString();
        $to    = now()->toDateString();

        $multiStation = $this->reportService->multiStationSummary($owner->id, $from, $to);

        return Inertia::render('OwnerDashboard', [
            'owner'        => $owner->load('stations'),
            'stationStats' => $multiStation,
            'from'         => $from,
            'to'           => $to,
        ]);
    }

    /**
     * Show station picker for owners.
     */
    public function selectStation(Request $request)
    {
        $user  = $request->user();

        // Super admins don't need station selection
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.owners');
        }

        $owner = $user->ownedAccount;

        if (! $owner) {
            // Non-owner users shouldn't be here — send them to dashboard
            return redirect()->route('dashboard');
        }

        $stations = $owner->stations()->orderBy('station_name')->get();

        // If owner has exactly one station, auto-select it
        if ($stations->count() === 1) {
            $request->session()->put('station_id', $stations->first()->id);
            return redirect()->route('dashboard');
        }

        return Inertia::render('SelectStation', [
            'owner'    => $owner,
            'stations' => $stations,
        ]);
    }

    /**
     * Set the active station in session for owner users.
     */
    public function switchStation(Request $request)
    {
        $request->validate([
            'station_id' => 'required|integer|exists:stations,id',
        ]);

        $user  = $request->user();
        $owner = $user->ownedAccount;

        // Verify the station belongs to this owner
        if (! $owner || ! $owner->stations()->where('id', $request->station_id)->exists()) {
            abort(403);
        }

        $request->session()->put('station_id', (int) $request->station_id);

        return redirect()->route('dashboard');
    }

    /**
     * Resolve the effective station for the current user.
     * Owners can switch stations via session; other users use their assigned station.
     */
    private function resolveStation($user, Request $request): ?Station
    {
        if ($user->isOwner() && $user->ownedAccount) {
            $sessionStationId = $request->session()->get('station_id');

            if ($sessionStationId) {
                $station = $user->ownedAccount->stations()->where('id', $sessionStationId)->first();
                if ($station) {
                    return $station;
                }
                // Invalid session station — clear it
                $request->session()->forget('station_id');
            }

            return null;
        }

        return $user->station;
    }
}
