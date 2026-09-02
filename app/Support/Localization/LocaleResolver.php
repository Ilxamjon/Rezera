<?php

namespace App\Support\Localization;

final class LocaleResolver
{
    public static function supported(): array
    {
        return config('rezera.supported_locales', ['uz', 'kaa', 'ru']);
    }

    public static function resolve(?string $preferred = null): string
    {
        if ($preferred !== null && in_array($preferred, self::supported(), true)) {
            return $preferred;
        }

        return config('rezera.default_locale', 'ru');
    }

    /**
     * Pick a localized string from a multilingual JSON object.
     *
     * @param  array<string, string>|null  $translations
     */
    public static function pick(?array $translations, ?string $locale = null, ?string $fallback = null): ?string
    {
        if ($translations === null || $translations === []) {
            return $fallback;
        }

        $locale = self::resolve($locale);

        return $translations[$locale]
            ?? $translations[config('rezera.default_locale', 'ru')]
            ?? $translations['ru']
            ?? $translations['uz']
            ?? $fallback
            ?? reset($translations) ?: null;
    }
}
