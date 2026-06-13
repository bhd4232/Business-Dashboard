<?php

namespace App\Filament\Forms\Components;

use App\Support\EmailFormat;
use Filament\Forms\Components\TextInput;

class EmailInput
{
    public static function make(string $field = 'email', string $label = 'Email'): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->email()
            ->regex(EmailFormat::PATTERN)
            ->type('text')
            ->inputMode('email')
            ->validationMessages([
                'email' => EmailFormat::MESSAGE,
                'regex' => EmailFormat::MESSAGE,
                'required' => 'Email address is required.',
            ])
            ->maxLength(255);
    }
}
