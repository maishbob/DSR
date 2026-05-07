<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    protected function authorizeStation(Model $model, string $stationFk = 'station_id'): void
    {
        $user = auth()->user();
        $stationId = $model->getAttribute($stationFk);

        if ($stationId !== $user->station_id && !$user->isOwner()) {
            abort(403);
        }
    }
}
