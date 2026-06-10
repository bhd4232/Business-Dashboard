<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait HasTrash
{
    /**
     * Soft delete a model.
     */
    protected function softDelete($model, string $label = 'Record'): JsonResponse
    {
        $model->delete();
        return response()->json(['message' => "{$label} moved to trash."]);
    }

    /**
     * Restore a soft-deleted model.
     */
    protected function restoreModel($model, string $label = 'Record'): JsonResponse
    {
        $model->restore();
        return response()->json(['message' => "{$label} restored successfully."]);
    }

    /**
     * Permanently delete (super admin only).
     */
    protected function purgeModel($model, string $label = 'Record'): JsonResponse
    {
        $this->authorize('admin.trash.purge');
        $model->forceDelete();
        return response()->json(['message' => "{$label} permanently deleted."]);
    }
}
