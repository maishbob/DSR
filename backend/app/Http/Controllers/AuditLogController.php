<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isManager() || $user->isSuperAdmin(), 403);

        $query = AuditLog::query()
            ->with(['user:id,name,email,role'])
            ->orderByDesc('created_at');

        if (!$user->isSuperAdmin()) {
            if ($user->isOwner()) {
                $stationIds = $user->ownedAccount?->stations()->pluck('id') ?? collect();
                $query->whereIn('station_id', $stationIds);
            } else {
                $query->where('station_id', $user->station_id);
            }
        }

        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }
        if ($modelType = $request->get('model_type')) {
            $query->where('model_type', $modelType);
        }
        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('model_type', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(50)->withQueryString();

        $scopeToStation = function ($q) use ($user) {
            if ($user->isOwner()) {
                $stationIds = $user->ownedAccount?->stations()->pluck('id') ?? collect();
                $q->whereIn('station_id', $stationIds);
            } else {
                $q->where('station_id', $user->station_id);
            }
        };

        $actions = AuditLog::query()
            ->when(!$user->isSuperAdmin(), $scopeToStation)
            ->select('action')->distinct()->orderBy('action')->pluck('action');

        $modelTypes = AuditLog::query()
            ->when(!$user->isSuperAdmin(), $scopeToStation)
            ->select('model_type')->distinct()->orderBy('model_type')->pluck('model_type');

        $users = User::query()
            ->when(!$user->isSuperAdmin(), fn($q) => $user->isOwner()
                ? $q->whereIn('station_id', $user->ownedAccount?->stations()->pluck('id') ?? collect())
                : $q->where('station_id', $user->station_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return Inertia::render('AuditLog/Index', [
            'logs'       => $logs,
            'filters'    => $request->only(['from', 'to', 'action', 'model_type', 'user_id', 'search']),
            'actions'    => $actions,
            'modelTypes' => $modelTypes,
            'users'      => $users,
        ]);
    }
}
