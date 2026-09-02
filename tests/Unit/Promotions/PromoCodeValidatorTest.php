<?php

namespace Tests\Unit\Promotions;

use App\Domain\Promotions\Enums\DiscountType;
use App\Models\PromoCode;
use App\Services\Promotions\PromoCodeValidator;
use App\Support\Promotions\PromoCodeNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PromoCodeValidatorTest extends TestCase
{
    #[DataProvider('normalizationProvider')]
    public function test_code_normalization(string $input, string $expected): void
    {
        $this->assertSame($expected, PromoCodeNormalizer::normalize($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizationProvider(): array
    {
        return [
            'lowercase' => ['game10', 'GAME10'],
            'mixed case' => ['Game10', 'GAME10'],
            'trimmed' => [' game10 ', 'GAME10'],
        ];
    }

    public function test_percentage_discount_calculation(): void
    {
        $validator = app(PromoCodeValidator::class);
        $promo = PromoCode::factory()->make([
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 10,
        ]);

        $this->assertSame(20_000, $validator->calculateDiscountAmount($promo, 200_000));
    }

    public function test_one_percent_of_one_uzs(): void
    {
        $validator = app(PromoCodeValidator::class);
        $promo = PromoCode::factory()->make([
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 1,
        ]);

        $this->assertSame(0, $validator->calculateDiscountAmount($promo, 1));
    }

    public function test_hundred_percent_discount(): void
    {
        $validator = app(PromoCodeValidator::class);
        $promo = PromoCode::factory()->make([
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 100,
        ]);

        $this->assertSame(50_000, $validator->calculateDiscountAmount($promo, 50_000));
    }
}
