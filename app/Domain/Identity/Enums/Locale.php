<?php

namespace App\Domain\Identity\Enums;

enum Locale: string
{
    case Uzbek = 'uz';
    case Karakalpak = 'kaa';
    case Russian = 'ru';

    public static function default(): self
    {
        return self::Russian;
    }

    public static function supported(): array
    {
        return array_map(
            static fn (self $locale): string => $locale->value,
            self::cases(),
        );
    }

    public static function isSupported(string $value): bool
    {
        return in_array($value, self::supported(), true);
    }
}
