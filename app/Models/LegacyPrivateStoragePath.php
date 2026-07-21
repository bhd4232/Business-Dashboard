<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class LegacyPrivateStoragePath extends Model
{
    protected $fillable = [
        'path',
        'company_id',
        'is_conflicted',
        'reference_count',
    ];

    protected $casts = [
        'is_conflicted' => 'boolean',
        'reference_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (LegacyPrivateStoragePath $record): void {
            $record->path = trim((string) $record->path);
            $record->path_hash = hash('sha256', $record->path);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function allows(string $path, int $companyId): bool
    {
        if (! Schema::hasTable('legacy_private_storage_paths')) {
            return false;
        }

        return static::query()
            ->where('path_hash', hash('sha256', trim($path)))
            ->where('company_id', $companyId)
            ->where('is_conflicted', false)
            ->exists();
    }
}
