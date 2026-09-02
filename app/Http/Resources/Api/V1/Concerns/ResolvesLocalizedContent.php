<?php

namespace App\Http\Resources\Api\V1\Concerns;

use App\Support\Localization\LocaleResolver;
use Illuminate\Http\Request;

trait ResolvesLocalizedContent
{
    protected function preferredLocale(Request $request): string
    {
        return LocaleResolver::resolve($request->header('Accept-Language'));
    }

    /**
     * @param  array<string, string>|null  $translations
     */
    protected function localized(?array $translations, Request $request, ?string $fallback = null): ?string
    {
        return LocaleResolver::pick($translations, $this->preferredLocale($request), $fallback);
    }
}
