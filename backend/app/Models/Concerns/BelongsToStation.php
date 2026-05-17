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
        $user = auth()->user();

        // Non-owner: scope to their directly assigned station.
        if ($user?->station_id) {
            return $this->where($field ?? $this->getRouteKeyName(), $value)
                ->where('station_id', $user->station_id)
                ->firstOrFail();
        }

        // Owner: use the station resolved and ownership-verified by ResolveStation middleware.
        if ($user?->isOwner() && $user->relationLoaded('station') && $user->station) {
            return $this->where($field ?? $this->getRouteKeyName(), $value)
                ->where('station_id', $user->station->id)
                ->firstOrFail();
        }

        abort(403);
    }
}
