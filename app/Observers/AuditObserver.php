<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditObserver
{
    private const HIDDEN_FIELDS = [
        'password',
        'remember_token',
        'email_verified_at',
        'created_at',
        'updated_at',
    ];

    public function created(Model $model): void
    {
        $this->record('created', $model, null, $this->sanitize($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $oldValues = Arr::only($model->getOriginal(), array_keys($model->getChanges()));
        $newValues = $model->getChanges();

        $oldValues = $this->sanitize($oldValues);
        $newValues = $this->sanitize($newValues);

        if ($oldValues === [] && $newValues === []) {
            return;
        }

        $this->record('updated', $model, $oldValues, $newValues);
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model, $this->sanitize($model->getOriginal()), null);
    }

    private function record(string $action, Model $model, ?array $oldValues, ?array $newValues): void
    {
        if ($model instanceof AuditLog) {
            return;
        }

        app(AuditLogService::class)->record($action, $model, $oldValues, $newValues);
    }

    private function sanitize(array $values): array
    {
        return Arr::except($values, self::HIDDEN_FIELDS);
    }
}
