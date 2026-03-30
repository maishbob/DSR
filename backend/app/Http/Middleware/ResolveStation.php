<?php

namespace App\Http\Middleware;

use App\Models\Station;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For owner users, resolve the active station from session.
 * Redirects to station picker if no station is selected.
 */
class ResolveStation
{
    /**
     * Routes that should be accessible without a station selected.
     */
    private array $except = [
        'select-station',
        'station.switch',
        'logout',
        'profile.edit',
        'profile.update',
        'profile.destroy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Non-owner users always have their assigned station
        if (! $user->isOwner() || ! $user->ownedAccount) {
            return $next($request);
        }

        // Allow exempt routes through without station
        $currentRoute = $request->route()?->getName();
        if ($currentRoute && in_array($currentRoute, $this->except)) {
            return $next($request);
        }

        $sessionStationId = $request->session()->get('station_id');

        if ($sessionStationId) {
            $station = $user->ownedAccount->stations()->where('id', $sessionStationId)->first();

            if ($station) {
                // Bind the resolved station onto the user relationship so
                // controllers can use $user->station seamlessly.
                $user->setRelation('station', $station);
                return $next($request);
            }

            // Session station no longer valid — clear it
            $request->session()->forget('station_id');
        }

        // No station selected — redirect to picker
        return redirect()->route('select-station');
    }
}
