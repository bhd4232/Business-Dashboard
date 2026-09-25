<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Enforces the bilingual rule (CLAUDE.md, docs/i18n-bangla-english-plan.md):
 * lang/en.json and lang/bn.json must always carry the same keys, no empty
 * values, and the same :placeholders in each translation.
 */
class TranslationParityTest extends TestCase
{
    public function test_english_and_bangla_files_have_identical_keys(): void
    {
        $en = $this->load('en');
        $bn = $this->load('bn');

        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($bn))), 'Keys missing from lang/bn.json');
        $this->assertSame([], array_values(array_diff(array_keys($bn), array_keys($en))), 'Keys missing from lang/en.json');
    }

    public function test_no_translation_is_empty_and_placeholders_match(): void
    {
        $en = $this->load('en');
        $bn = $this->load('bn');

        foreach ($en as $key => $english) {
            $this->assertNotSame('', trim((string) $english), "Empty English value for [{$key}]");
            $this->assertNotSame('', trim((string) ($bn[$key] ?? '')), "Empty Bangla value for [{$key}]");
            $this->assertSame(
                $this->placeholders($key),
                $this->placeholders((string) $english),
                "English value placeholders differ from key [{$key}]"
            );
            $this->assertSame(
                $this->placeholders($key),
                $this->placeholders((string) ($bn[$key] ?? '')),
                "Bangla value placeholders differ from key [{$key}]"
            );
        }
    }

    /** @return array<string, string> */
    private function load(string $locale): array
    {
        $path = dirname(__DIR__, 2)."/lang/{$locale}.json";
        $this->assertFileExists($path);

        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /** @return list<string> */
    private function placeholders(string $text): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $text, $matches);
        $names = array_unique($matches[1]);
        sort($names);

        return array_values($names);
    }
}
