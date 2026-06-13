<?php

namespace Tests\Unit;

use App\Filament\Forms\Components\EmailInput;
use Tests\TestCase;

class EmailInputTest extends TestCase
{
    public function test_email_input_uses_filament_validation_instead_of_native_browser_validation(): void
    {
        $input = EmailInput::make();

        $this->assertSame('text', $input->getType());
        $this->assertSame('email', $input->getInputMode());
        $this->assertSame('Please enter a valid email address like test@domain.com.', $input->getValidationMessages()['email']);
        $this->assertSame('Please enter a valid email address like test@domain.com.', $input->getValidationMessages()['regex']);
    }
}
