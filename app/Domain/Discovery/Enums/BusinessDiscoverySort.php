<?php

namespace App\Domain\Discovery\Enums;

enum BusinessDiscoverySort: string
{
    case Relevance = 'relevance';
    case Newest = 'newest';
    case Name = 'name';
    case Nearest = 'nearest';
    case PriceLow = 'price_low';
    case PriceHigh = 'price_high';
    case Popular = 'popular';
    case Rating = 'rating';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $sort): string => $sort->value, self::cases());
    }

    public function requiresGeo(): bool
    {
        return $this === self::Nearest;
    }
}
