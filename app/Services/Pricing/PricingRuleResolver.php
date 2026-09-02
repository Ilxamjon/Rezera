<?php

namespace App\Services\Pricing;

use App\Domain\Pricing\Enums\PricingType;
use App\Models\PricingRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class PricingRuleResolver
{
    /**
     * @param  Collection<int, PricingRule>  $rules
     */
    public function resolveForMoment(
        Collection $rules,
        CarbonImmutable $moment,
        string $timezone,
        ?string $resourceId,
        ?string $resourceGroupId,
    ): ?PricingRule {
        $candidates = $rules->filter(fn (PricingRule $rule): bool => $this->matchesMoment(
            $rule,
            $moment,
            $timezone,
            $resourceId,
            $resourceGroupId,
        ));

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortBy([
                fn (PricingRule $rule): int => -$this->specificityScore($rule),
                fn (PricingRule $rule): int => -$rule->priority,
                fn (PricingRule $rule): string => $rule->id,
            ])
            ->first();
    }

    /**
     * @param  Collection<int, PricingRule>  $rules
     */
    public function resolveFixedForInterval(
        Collection $rules,
        CarbonImmutable $localStart,
        CarbonImmutable $localEnd,
        string $timezone,
        ?string $resourceId,
        ?string $resourceGroupId,
    ): ?PricingRule {
        $fixedRules = $rules->filter(fn (PricingRule $rule): bool => $rule->pricing_type === PricingType::Fixed);

        $candidates = $fixedRules->filter(fn (PricingRule $rule): bool => $this->coversInterval(
            $rule,
            $localStart,
            $localEnd,
            $timezone,
            $resourceId,
            $resourceGroupId,
        ));

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortBy([
                fn (PricingRule $rule): int => -$this->specificityScore($rule),
                fn (PricingRule $rule): int => -$rule->priority,
                fn (PricingRule $rule): string => $rule->id,
            ])
            ->first();
    }

    public function matchesMoment(
        PricingRule $rule,
        CarbonImmutable $moment,
        string $timezone,
        ?string $resourceId,
        ?string $resourceGroupId,
    ): bool {
        if (! $this->matchesScope($rule, $resourceId, $resourceGroupId)) {
            return false;
        }

        if (! $this->matchesDate($rule, $moment, $timezone)) {
            return false;
        }

        if ($rule->day_of_week !== null || $rule->start_time !== null || $rule->end_time !== null) {
            return $this->momentWithinTimeWindow($rule, $moment, $timezone);
        }

        return true;
    }

    public function coversInterval(
        PricingRule $rule,
        CarbonImmutable $localStart,
        CarbonImmutable $localEnd,
        string $timezone,
        ?string $resourceId,
        ?string $resourceGroupId,
    ): bool {
        if (! $this->matchesScope($rule, $resourceId, $resourceGroupId)) {
            return false;
        }

        $cursor = $localStart->copy();

        while ($cursor->lessThan($localEnd)) {
            if (! $this->matchesMoment($rule, $cursor, $timezone, $resourceId, $resourceGroupId)) {
                return false;
            }

            $cursor = $cursor->addMinute();
        }

        return true;
    }

    public function specificityScore(PricingRule $rule): int
    {
        $score = 0;

        if ($rule->specific_date !== null) {
            $score += 1_000;
        }

        if ($rule->starts_at !== null || $rule->ends_at !== null) {
            $score += 800;
        }

        if ($rule->day_of_week !== null) {
            $score += 100;
        }

        if ($rule->start_time !== null || $rule->end_time !== null) {
            $score += 50;
        }

        if ($rule->resource_id !== null) {
            $score += 30;
        } elseif ($rule->resource_group_id !== null) {
            $score += 20;
        } else {
            $score += 10;
        }

        return $score;
    }

    private function matchesScope(PricingRule $rule, ?string $resourceId, ?string $resourceGroupId): bool
    {
        if ($rule->resource_id !== null) {
            return $rule->resource_id === $resourceId;
        }

        if ($rule->resource_group_id !== null) {
            return $rule->resource_group_id === $resourceGroupId;
        }

        return true;
    }

    private function matchesDate(PricingRule $rule, CarbonImmutable $moment, string $timezone): bool
    {
        $local = $moment->timezone($timezone);

        if ($rule->specific_date !== null) {
            return $local->toDateString() === $rule->specific_date->format('Y-m-d');
        }

        if ($rule->starts_at !== null && $moment->lessThan($rule->starts_at)) {
            return false;
        }

        if ($rule->ends_at !== null && $moment->greaterThan($rule->ends_at)) {
            return false;
        }

        if ($rule->day_of_week !== null) {
            return (int) $local->isoWeekday() === (int) $rule->day_of_week;
        }

        return true;
    }

    private function momentWithinTimeWindow(PricingRule $rule, CarbonImmutable $moment, string $timezone): bool
    {
        $local = $moment->timezone($timezone);
        $minutes = ((int) $local->format('H')) * 60 + (int) $local->format('i');

        if ($rule->start_time === null || $rule->end_time === null) {
            return $rule->day_of_week === null || (int) $local->isoWeekday() === (int) $rule->day_of_week;
        }

        $startMinutes = $this->minutesFromTime((string) $rule->start_time);
        $endMinutes = $this->minutesFromTime((string) $rule->end_time);

        if ($endMinutes > $startMinutes) {
            return $minutes >= $startMinutes && $minutes < $endMinutes;
        }

        return $minutes >= $startMinutes || $minutes < $endMinutes;
    }

    private function minutesFromTime(string $time): int
    {
        $parts = explode(':', $time);
        $hours = (int) ($parts[0] ?? 0);
        $mins = (int) ($parts[1] ?? 0);

        return ($hours * 60) + $mins;
    }
}
