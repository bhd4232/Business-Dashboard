<?php

namespace Tests\Unit;

use App\Support\MoneyFormatter;
use PHPUnit\Framework\TestCase;

class MoneyFormatterTest extends TestCase
{
    public function test_it_removes_only_non_meaningful_decimal_zeroes(): void
    {
        $this->assertSame('0', MoneyFormatter::number(0));
        $this->assertSame('1,625,000', MoneyFormatter::number(1625000));
        $this->assertSame('2,520.5', MoneyFormatter::number(2520.50));
        $this->assertSame('2,520.25', MoneyFormatter::number(2520.25));
        $this->assertSame('৳', MoneyFormatter::symbol());
        $this->assertSame('$', MoneyFormatter::symbol('USD'));
        $this->assertSame('BDT (৳)', MoneyFormatter::label());
        $this->assertSame('$ 2,520.5', MoneyFormatter::currency(2520.50, 'USD'));
    }
}
