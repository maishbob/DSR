<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Scope route-model binding to the authenticated user's station.
 *
 * Apply this trait to any model that has a direct `station_id` column.
 * It overrides resolveRouteBinding() so that implicit route-model binding
 * will 404 if the record doesn't belong to the current user's station.
 */
trait BelongsToStation
{
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $stationId = auth()->user()?->station_id;

        if (! $stationId) {
            abort(403);
        }

        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('station_id', $stationId)
            ->firstOrFail();
    }
}
