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
     * Nixpacks' own renderer (scripts/config/template.mjs) resolves
     * $if(COND) (...) else (...) with a single-pass, NON-GREEDY regex that
     * cannot parse nested parentheses -- it stops each capture group at the
     * first literal ")" it finds. A prior fix here nested a second $if
     * inside the first's else-branch to avoid two independent blocks
     * rendering at once ("duplicate location \"/\""); that text looked
     * correctly nested but the regex mis-captured it, leaving stray "(",
     * ")" and "else ()" fragments in the rendered nginx.conf and crashing
     * nginx immediately on container start (caught on Coolify staging,
     * 2026-08-01, second deploy). This test actually renders the template
     * with a faithful PHP port of Nixpacks' algorithm -- checking the raw
     * source text is not enough, since broken nesting can still look right
     * as plain text.
     */
    public function test_nixpacks_template_renders_without_leftover_conditional_syntax(): void
    {
        $template = file_get_contents(base_path('nginx.template.conf'));
        $this->assertIsString($template);

        foreach ([
            ['IS_LARAVEL' => 'yes', 'NIXPACKS_PHP_ROOT_DIR' => '/app/public', 'PORT' => '80'],
            ['IS_LARAVEL' => 'yes', 'NIXPACKS_PHP_ROOT_DIR' => '/app/public', 'NIXPACKS_PHP_FALLBACK_PATH' => '/index.php', 'PORT' => '80'],
        ] as $env) {
            $rendered = $this->renderNixpacksTemplate($template, $env);

            $this->assertSame(
                1,
                substr_count($rendered, 'location / {'),
                "Expected exactly one \"location /\" block for env: ".json_encode($env),
            );
            $this->assertStringNotContainsString('$if(', $rendered);
            $this->assertStringNotContainsString('else (', $rendered);
        }
    }

    /**
     * Faithful port of Nixpacks' replaceStr() from scripts/config/template.mjs:
     * a single global, non-greedy regex pass for $if/else, recursing only into
     * captured branch text -- deliberately reproducing its inability to parse
     * nested parentheses so this test catches the same class of bug Nixpacks
     * itself would hit.
     */
    private function renderNixpacksTemplate(string $input, array $env): string
    {
        $withIf = preg_replace_callback(
            '/\$if\s*\((\w+)\)\s*\((.*?)\)\s*else\s*\((.*?)\)/s',
            function (array $m) use ($env) {
                $branch = (($env[$m[1]] ?? '') !== '') ? $m[2] : $m[3];

                return $this->renderNixpacksTemplate($branch, $env);
            },
            $input,
        );

        return preg_replace_callback(
            '/\$\{(\w+)\}/',
            fn (array $m) => $env[$m[1]] ?? '',
            $withIf,
        );
    }
}
