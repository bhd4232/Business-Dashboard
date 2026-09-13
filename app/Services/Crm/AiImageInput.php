<?php

namespace App\Services\Crm;

use App\Models\ConversationMessage;
use App\Services\CompanyStorageService;
use Illuminate\Support\Facades\Storage;

class AiImageInput
{
    public function block(ConversationMessage $message): ?array
    {
        $company = $message->conversation->company;
        $storage = app(CompanyStorageService::class);
        $location = $storage->locatePrivate($message->media_path, $company);
        if (! $location || Storage::disk($location['disk'])->size($location['path']) > 8 * 1024 * 1024) {
            return null;
        }
        $bytes = $storage->readPrivate($message->media_path, $company);
        $info = $bytes ? @getimagesizefromstring($bytes) : false;
        if (! $info || $info[0] * $info[1] > 20000000 || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }
        $original = @imagecreatefromstring($bytes);
        if (! $original) {
            return null;
        }
        $scale = min(1, 1024 / max($info[0], $info[1]));
        $resized = imagescale($original, max(1, (int) ($info[0] * $scale)), max(1, (int) ($info[1] * $scale)));
        ob_start();
        imagejpeg($resized, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($original);
        imagedestroy($resized);

        return ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => base64_encode($jpeg)]];
    }
}
