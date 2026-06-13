<?php

namespace Tests\Unit;

use App\Filament\Forms\Components\PhoneInput;
use App\Filament\Forms\Components\PhoneNumberInput;
use PHPUnit\Framework\TestCase;

class PhoneInputTest extends TestCase
{
    public function test_it_formats_local_phone_number_with_country_code(): void
    {
        $this->assertSame('+8801712345678', PhoneInput::formatPhoneNumber('01712345678', '+880'));
        $this->assertSame('+8613800138000', PhoneInput::formatPhoneNumber('13800138000', '+86'));
    }

    public function test_it_preserves_existing_international_phone_number(): void
    {
        $this->assertSame('+971501234567', PhoneInput::formatPhoneNumber('+971 50 123 4567', '+880'));
    }

    public function test_it_splits_known_country_code_from_phone_number(): void
    {
        $this->assertSame(['+86', '13800138000'], PhoneInput::splitPhoneNumber('+8613800138000'));
    }

    public function test_it_includes_a_full_country_calling_code_list(): void
    {
        $countries = PhoneNumberInput::countryOptionsData();

        $this->assertGreaterThan(200, count($countries));
        $this->assertContains('Bangladesh', array_column($countries, 'country'));
        $this->assertContains('China', array_column($countries, 'country'));
        $this->assertContains('United States', array_column($countries, 'country'));
    }
}
