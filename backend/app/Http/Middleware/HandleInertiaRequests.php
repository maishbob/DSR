<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'isSuperAdmin' => $user?->isSuperAdmin() ?? false,
            ],
            'currentStation' => function () use ($user) {
                $station = $user?->station;
                return $station ? [
                    'id'           => $station->id,
                    'station_name' => $station->station_name,
                    'location'     => $station->location,
                ] : null;
            },
            'flash' => [
                'success'     => fn() => $request->session()->get('success'),
                'error'       => fn() => $request->session()->get('error'),
                'importStats' => fn() => $request->session()->get('importStats'),
            ],
        ];
    }
}
