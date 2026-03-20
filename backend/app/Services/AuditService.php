<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(
        string $action,
        Model $model,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $stationId = null
    ): void {
        AuditLog::create([
            'user_id'    => Auth::id(),
            'station_id' => $stationId ?? (Auth::user()?->station_id),
            'action'     => $action,
            'model_type' => class_basename($model),
            'model_id'   => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
        ]);
    }
}
