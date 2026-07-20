<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Builds a public URL for a path stored on the "public" disk.
 *
 * `asset('storage/'.$path)` only works because of the local storage:link
 * symlink — it hardcodes "this app's own domain + /storage/". That
 * assumption breaks the moment the "public" disk is pointed at Cloudflare
 * R2 (see StorageSettingsService/AppServiceProvider::configureCloudStorage()),
 * because R2 files are served from R2's own URL, not this app's domain.
 *
 * `Storage::disk('public')->url($path)` is disk-aware and returns the
 * correct URL either way, so every place that used to build a storage URL
 * with `asset()` should go through here instead.
 */
class StorageUrl
{
    public static function for(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
