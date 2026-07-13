<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AdminPassword
{
    public static function fromEnvironment(): string
    {
        $password = config('app.seed_admin_password');

        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException('ADMIN_PASSWORD is required before running the database seeder.');
        }

        self::assertStrong($password, 'ADMIN_PASSWORD');

        return $password;
    }

    public static function assertStrong(string $password, string $label = 'Password'): void
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', self::rule()]],
        );

        if ($validator->fails()) {
            throw new RuntimeException(
                "{$label} must be at least 12 characters and include uppercase and lowercase letters, numbers, and symbols.",
            );
        }
    }

    public static function rule(): Password
    {
        return Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols();
    }
}
