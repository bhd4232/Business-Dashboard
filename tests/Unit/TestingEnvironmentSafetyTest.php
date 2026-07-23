<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TestingEnvironmentSafetyTest extends TestCase
{
    public function test_artisan_testing_environment_cannot_fall_back_to_a_persisted_database(): void
    {
        $environment = parse_ini_file(
            dirname(__DIR__, 2).'/.env.testing',
            scanner_mode: INI_SCANNER_RAW,
        );

        $this->assertIsArray($environment);
        $this->assertSame('testing', $environment['APP_ENV'] ?? null);
        $this->assertSame('sqlite', $environment['DB_CONNECTION'] ?? null);
        $this->assertSame(':memory:', $environment['DB_DATABASE'] ?? null);
        $this->assertSame(':memory:', $environment['DEMO_DB_DATABASE'] ?? null);
        $this->assertNotSame('demo', $environment['DB_CONNECTION'] ?? null);
    }
}
