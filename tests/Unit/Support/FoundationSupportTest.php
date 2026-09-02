<?php

namespace Tests\Unit\Support;

use App\Support\Money\Money;
use App\Support\Phone\PhoneNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FoundationSupportTest extends TestCase
{
    #[Test]
    public function it_calculates_hourly_money_using_integer_math(): void
    {
        $money = Money::hourlyTotal(20_000, 180);

        $this->assertSame(60_000, $money->amount);
        $this->assertSame('UZS', $money->currency);
    }

    #[Test]
    public function it_normalizes_uzbekistan_phone_numbers_to_e164(): void
    {
        $this->assertSame('+998901234567', PhoneNormalizer::normalize('90 123 45 67'));
        $this->assertSame('+998901234567', PhoneNormalizer::normalize('998901234567'));
    }
}
