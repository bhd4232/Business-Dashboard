<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\CompanyStorageService;
use App\Support\CompanyMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * One row per image stored in a company's Media Hub — every image an admin
 * user has ever uploaded through any FileUpload field (the "new upload"
 * side), plus anything added directly on the Media Hub page itself.
 *
 * The `path` column is deliberately the same disk-relative path format every
 * image FileUpload field already stores (see App\Support\CompanyMedia and
 * App\Services\CompanyStorageService), so picking a Media row from the "Select
 * From Media" action just drops its path straight into the target field —
 * no conversion, no extra join needed to render it anywhere images already
 * render.
 */
class Media extends Model
{
    use BelongsToCompany;

    protected $table = 'media';

    protected $fillable = [
        'company_id',
        'path',
        'original_filename',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function uploader(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Register an already-stored file in the Media Hub. Called from
     * App\Filament\Concerns\OptimizesUploadedImages so every image saved
     * through that shared upload path automatically shows up in the Media
     * Hub, and from the Media Hub's own upload action for files added there
     * directly.
     *
     * mime_type/size are read back from the disk rather than the original
     * TemporaryUploadedFile, since ImageOptimizerService re-encodes most
     * raster uploads to WebP — the original file's type/size would describe
     * a file that no longer exists.
     *
     * Silently does nothing when no company can be resolved (matches
     * CompanyMedia's own fail-soft resolve() contract) rather than blocking
     * the image upload itself over a Media Hub bookkeeping failure.
     */
    public static function recordUpload(
        string $disk,
        string $path,
        mixed $record = null,
        mixed $companyId = null,
        ?TemporaryUploadedFile $file = null,
    ): ?self {
        $company = CompanyMedia::resolve($record, $companyId);

        if (! $company) {
            return null;
        }

        return static::query()->create([
            'company_id' => $company->getKey(),
            'path' => $path,
            'original_filename' => $file?->getClientOriginalName(),
            'mime_type' => rescue(fn (): string|false => Storage::disk($disk)->mimeType($path), rescue: false, report: false) ?: null,
            'size' => rescue(fn (): int => Storage::disk($disk)->size($path), rescue: 0, report: false) ?: null,
            'uploaded_by' => Auth::id(),
        ]);
    }

    /**
     * Best-effort removal of the underlying storage object. Locates the
     * object across active/legacy/configured disks exactly like every other
     * public read in the app (CompanyStorageService::locatePublic), so a
     * Media Hub delete works the same whether the file lives on the local
     * "public" disk or on R2.
     */
    public function deleteFile(): void
    {
        $location = app(CompanyStorageService::class)->locatePublic($this->path, $this->company);

        if ($location === null) {
            return;
        }

        rescue(fn () => Storage::disk($location['disk'])->delete($location['path']), report: false);
    }
}
