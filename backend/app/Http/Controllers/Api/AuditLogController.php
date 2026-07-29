<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('user:id,name,username');
        if ($request->filled('action')) $query->where('action', $request->input('action'));
        if ($request->filled('user_id')) $query->where('user_id', $request->integer('user_id'));
        if ($request->filled('from_date')) $query->whereDate('created_at', '>=', $request->date('from_date'));
        if ($request->filled('to_date')) $query->whereDate('created_at', '<=', $request->date('to_date'));
        if ($request->filled('search')) {
            $term = '%'.trim((string) $request->input('search')).'%';
            $query->where(fn ($query) => $query->where('action', 'like', $term)
                ->orWhere('entity_type', 'like', $term)->orWhere('entity_id', 'like', $term)
                ->orWhere('ip_address', 'like', $term)
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $term)->orWhere('username', 'like', $term)));
        }

        $logs = $query->latest()->paginate(min($request->integer('per_page', 50), 100));
        $logs->getCollection()->transform(function (AuditLog $log) {
            $log->old_values = $this->sanitize($log->old_values);
            $log->new_values = $this->sanitize($log->new_values);
            return $log;
        });
        return response()->json(['success' => true, 'message' => 'Thành công', 'data' => [
            'logs' => $logs,
            'users' => User::query()->whereHas('auditLogs')->orderBy('name')->get(['id', 'name', 'username']),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]]);
    }

    private function sanitize(?array $values): ?array
    {
        if ($values === null) return null;
        $settingIsSecret = isset($values['key']) && preg_match('/password|secret|token/i', (string) $values['key']);
        foreach ($values as $key => $value) {
            if (preg_match('/password|secret|token/i', (string) $key) || ($settingIsSecret && $key === 'value')) {
                $values[$key] = '••••••••';
            } elseif (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }
        return $values;
    }
}
