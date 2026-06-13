<?php

namespace App\Models\Concerns;

use App\Support\EmailFormat;
use Illuminate\Validation\ValidationException;

trait ValidatesEmailAddress
{
    protected static function validateEmailAttribute(object $model, bool $required = false): void
    {
        $email = trim((string) ($model->email ?? ''));

        if ($email === '') {
            if ($required) {
                throw ValidationException::withMessages([
                    'email' => 'Email address is required.',
                ]);
            }

            $model->email = null;

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! preg_match(EmailFormat::PATTERN, $email)) {
            throw ValidationException::withMessages([
                'email' => EmailFormat::MESSAGE,
            ]);
        }

        $model->email = $email;
    }
}
