<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public function record(
        string $action,
        string|Model $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
    ): void {
        $request ??= request();
        $auditableType = is_string($auditable) ? $auditable : $auditable::class;
        $auditableId = is_string($auditable) ? null : $auditable->getKey();

        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
