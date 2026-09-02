<?php

namespace App\Services\Pricing;

use App\Domain\Pricing\Enums\PricingType;
use App\Models\PricingRule;
use App\Models\Resource;
use App\Models\ResourceGroup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PricingEngine
{
    public function __construct(
        private readonly PricingRuleResolver $resolver,
        private readonly PricingIntervalSplitter $splitter,
    ) {}

    public function calculate(PricingContext $context): PricingResult
    {
        $resource = $context->resource->loadMissing('group');
        $currency = $resource->currency
            ?? $resource->group?->default_currency
            ?? config('rezera.default_currency', 'UZS');

        $rules = $this->loadRules($context);
        $localStart = $context->localStart();
        $localEnd = $context->localEnd();

        $fixedRule = $this->resolver->resolveFixedForInterval(
            $rules,
            $localStart,
            $localEnd,
            $context->timezone,
            $resource->id,
            $resource->resource_group_id,
        );

        if ($fixedRule !== null) {
            return $this->buildFixedResult($fixedRule, $context, $currency);
        }

        $segments = [];
        $appliedRules = [];
        $subtotal = 0;

        foreach ($this->splitter->split($localStart, $localEnd, $context->timezone, $rules) as [$segmentStart, $segmentEnd]) {
            $midpoint = $segmentStart->addMinutes((int) max(1, $segmentStart->diffInMinutes($segmentEnd)) / 2);
            $rule = $this->resolver->resolveForMoment(
                $rules->filter(fn (PricingRule $candidate): bool => $candidate->pricing_type === PricingType::Hourly),
                $midpoint,
                $context->timezone,
                $resource->id,
                $resource->resource_group_id,
            );

            $unitPrice = $rule?->price ?? $this->baseHourlyRate($resource);
            $minutes = (int) $segmentStart->diffInMinutes($segmentEnd);
            $amount = (int) round($unitPrice * ($minutes / 60));
            $subtotal += $amount;

            if ($rule !== null) {
                $appliedRules[$rule->id] = [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'pricing_type' => $rule->pricing_type->value,
                    'price' => $rule->price,
                ];
            }

            $segments[] = new PricingSegment(
                startTime: $segmentStart->format('H:i'),
                endTime: $segmentEnd->format('H:i'),
                unitPrice: (int) $unitPrice,
                amount: $amount,
                pricingType: PricingType::Hourly->value,
                ruleName: $rule?->name,
            );
        }

        $hourlyRate = $this->baseHourlyRate($resource);

        return new PricingResult(
            currency: $currency,
            subtotal: max($subtotal, 0),
            hourlyRateAmount: $hourlyRate,
            segments: $segments,
            snapshot: ['applied_rules' => array_values($appliedRules)],
        );
    }

    /**
     * @return Collection<int, PricingRule>
     */
    private function loadRules(PricingContext $context): Collection
    {
        return PricingRule::query()
            ->active()
            ->where('business_id', $context->business->id)
            ->where(function ($query) use ($context): void {
                $query
                    ->whereNull('resource_id')
                    ->orWhere('resource_id', $context->resource->id);
            })
            ->where(function ($query) use ($context): void {
                $query
                    ->whereNull('resource_group_id')
                    ->orWhere('resource_group_id', $context->resource->resource_group_id);
            })
            ->orderByDesc('priority')
            ->get();
    }

    private function buildFixedResult(PricingRule $rule, PricingContext $context, string $currency): PricingResult
    {
        if ($rule->currency !== null && $rule->currency !== $currency) {
            throw ValidationException::withMessages([
                'resource_id' => [__('pricing.currency_mismatch')],
            ]);
        }

        $segment = new PricingSegment(
            startTime: $context->localStart()->format('H:i'),
            endTime: $context->localEnd()->format('H:i'),
            unitPrice: (int) $rule->price,
            amount: (int) $rule->price,
            pricingType: PricingType::Fixed->value,
            ruleName: $rule->name,
        );

        return new PricingResult(
            currency: $currency,
            subtotal: (int) $rule->price,
            hourlyRateAmount: $this->baseHourlyRate($context->resource),
            segments: [$segment],
            snapshot: [
                'applied_rules' => [[
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'pricing_type' => $rule->pricing_type->value,
                    'price' => $rule->price,
                ]],
            ],
        );
    }

    private function baseHourlyRate(Resource $resource): int
    {
        if ($resource->hourly_rate_amount !== null) {
            return (int) $resource->hourly_rate_amount;
        }

        if ($resource->resource_group_id !== null) {
            $group = $resource->relationLoaded('group')
                ? $resource->group
                : ResourceGroup::query()->find($resource->resource_group_id);

            if ($group?->default_hourly_rate_amount !== null) {
                return (int) $group->default_hourly_rate_amount;
            }
        }

        throw ValidationException::withMessages([
            'resource_id' => [__('reservations.resource_missing_price')],
        ]);
    }
}
