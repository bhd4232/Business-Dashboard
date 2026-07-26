<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LivewireTemporaryUploadConfigurationTest extends TestCase
{
    public function test_livewire_temporary_uploads_use_a_dedicated_local_disk(): void
    {
        $disk = (string) config('livewire.temporary_file_upload.disk');

        $this->assertSame('livewire-tmp', $disk);
        $this->assertSame('local', config("filesystems.disks.{$disk}.driver"));
        $this->assertNotSame(config('filesystems.default'), $disk);
        $this->assertSame('livewire-tmp', config('livewire.temporary_file_upload.directory'));

        Storage::disk($disk)->put('health/check.txt', 'ok');

        $this->assertSame('ok', Storage::disk($disk)->get('health/check.txt'));

        Storage::disk($disk)->deleteDirectory('health');
    }

    public function test_nixpacks_template_accepts_the_application_upload_limits(): void
    {
        $template = file_get_contents(base_path('nginx.template.conf'));

        $this->assertIsString($template);
        $this->assertStringContainsString('client_max_body_size 16M;', $template);
        $this->assertStringContainsString('upload_max_filesize=12M', $template);
        $this->assertStringContainsString('post_max_size=16M', $template);
        $this->assertStringContainsString('fastcgi_param PHP_VALUE', $template);
    }
}
