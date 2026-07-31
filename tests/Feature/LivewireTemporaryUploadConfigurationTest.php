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

    /**
     * IS_LARAVEL and NIXPACKS_PHP_FALLBACK_PATH are both true for this repo,
     * so the two "location /" blocks must be nested ($if...else($if...)),
     * never two independent top-level $if blocks -- both would render at
     * once and nginx fails to start with "duplicate location \"/\"" ,
     * crash-looping the container. This regression was caught on a fresh
     * Coolify staging build (2026-08-01).
     */
    public function test_nixpacks_template_never_renders_two_independent_root_location_blocks(): void
    {
        $template = file_get_contents(base_path('nginx.template.conf'));

        $this->assertIsString($template);
        $this->assertStringContainsString(
            "\$if(IS_LARAVEL) (\n            location / {\n                try_files \$uri \$uri/ /index.php?\$query_string;\n            }\n        ) else (\n            \$if(NIXPACKS_PHP_FALLBACK_PATH) (",
            $template,
        );
    }
}
