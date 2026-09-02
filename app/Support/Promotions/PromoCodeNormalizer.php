<?php

namespace App\Support\Promotions;

final class PromoCodeNormalizer
{
    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }
}
