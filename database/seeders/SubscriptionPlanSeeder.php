<?php

namespace Database\Seeders;

use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Domain\Subscriptions\Enums\EntitlementValueType;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\SubscriptionFeature;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\PlanEntitlement;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\SubscriptionPlanResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = $this->planDefinitions();

        foreach ($plans as $definition) {
            $plan = SubscriptionPlan::query()->updateOrCreate(
                ['code' => $definition['code']],
                collect($definition)->except(['entitlements'])->all(),
            );

            foreach ($definition['entitlements'] as $feature => $entitlement) {
                PlanEntitlement::query()->updateOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'feature_code' => $feature,
                    ],
                    [
                        'value_type' => $entitlement['type'],
                        'value' => (string) $entitlement['value'],
                    ],
                );
            }

            app(SubscriptionPlanResolver::class)->forgetPlanCache($plan);
        }

        if (! app()->environment('testing')) {
            $this->assignMissingDefaultSubscriptions();
        }
    }

    private function assignMissingDefaultSubscriptions(): void
    {
        $defaultPlan = app(SubscriptionPlanResolver::class)->defaultPlan();
        $now = now();

        Business::query()
            ->whereDoesntHave('subscriptions', fn ($query) => $query->effective())
            ->orderBy('id')
            ->chunkById(200, function ($businesses) use ($defaultPlan, $now): void {
                foreach ($businesses as $business) {
                    DB::transaction(function () use ($business, $defaultPlan, $now): void {
                        $exists = BusinessSubscription::query()
                            ->where('business_id', $business->id)
                            ->effective()
                            ->exists();

                        if ($exists) {
                            return;
                        }

                        BusinessSubscription::query()->create([
                            'business_id' => $business->id,
                            'plan_id' => $defaultPlan->id,
                            'status' => SubscriptionStatus::Active,
                            'billing_interval' => BillingInterval::Monthly,
                            'started_at' => $now,
                            'current_period_start' => $now,
                            'current_period_end' => $now->copy()->addMonth(),
                        ]);
                    });
                }
            });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function planDefinitions(): array
    {
        return [
            [
                'code' => 'free',
                'name' => 'Free',
                'description' => 'Starter plan for new businesses.',
                'translations' => [
                    'uz' => ['name' => 'Bepul', 'description' => 'Yangi bizneslar uchun boshlang\'ich reja.'],
                    'ru' => ['name' => 'Бесплатный', 'description' => 'Стартовый план для новых бизнесов.'],
                    'kaa' => ['name' => 'Teńsiz', 'description' => 'Jańa biznesler ushın baslanǵısh reja.'],
                ],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 10,
                'monthly_price' => 0,
                'yearly_price' => 0,
                'currency' => 'UZS',
                'trial_days' => 0,
                'entitlements' => $this->entitlements([
                    SubscriptionFeature::RESOURCES_MAX => 5,
                    SubscriptionFeature::RESOURCE_CATEGORIES_MAX => 3,
                    SubscriptionFeature::STAFF_MAX => 2,
                    SubscriptionFeature::RESERVATIONS_MONTHLY_MAX => 200,
                    SubscriptionFeature::ANALYTICS_BASIC => true,
                    SubscriptionFeature::ANALYTICS_ADVANCED => false,
                    SubscriptionFeature::PRICING_ADVANCED => false,
                    SubscriptionFeature::PROMO_CODES => false,
                    SubscriptionFeature::REVIEWS => true,
                ]),
            ],
            [
                'code' => 'basic',
                'name' => 'Basic',
                'description' => 'Essential tools for growing businesses.',
                'translations' => [
                    'uz' => ['name' => 'Basic', 'description' => 'O\'sib borayotgan bizneslar uchun asosiy vositalar.'],
                    'ru' => ['name' => 'Базовый', 'description' => 'Основные инструменты для растущего бизнеса.'],
                    'kaa' => ['name' => 'Basic', 'description' => 'Ósim biznesler ushın negizgi quraldar.'],
                ],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 20,
                'monthly_price' => 299000,
                'yearly_price' => 2990000,
                'currency' => 'UZS',
                'trial_days' => 14,
                'entitlements' => $this->entitlements([
                    SubscriptionFeature::RESOURCES_MAX => 20,
                    SubscriptionFeature::RESOURCE_CATEGORIES_MAX => 10,
                    SubscriptionFeature::STAFF_MAX => 5,
                    SubscriptionFeature::RESERVATIONS_MONTHLY_MAX => 1000,
                    SubscriptionFeature::ANALYTICS_BASIC => true,
                    SubscriptionFeature::ANALYTICS_ADVANCED => false,
                    SubscriptionFeature::PRICING_ADVANCED => true,
                    SubscriptionFeature::PROMO_CODES => false,
                    SubscriptionFeature::REVIEWS => true,
                ]),
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'description' => 'Advanced analytics and promotions for professional teams.',
                'translations' => [
                    'uz' => ['name' => 'Pro', 'description' => 'Professional jamoalar uchun kengaytirilgan tahlil va aksiyalar.'],
                    'ru' => ['name' => 'Про', 'description' => 'Расширенная аналитика и акции для профессиональных команд.'],
                    'kaa' => ['name' => 'Pro', 'description' => 'Professional toparlar ushın keńeytilgen analitika hám aksiyalar.'],
                ],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 30,
                'monthly_price' => 990000,
                'yearly_price' => 9900000,
                'currency' => 'UZS',
                'trial_days' => 14,
                'entitlements' => $this->entitlements([
                    SubscriptionFeature::RESOURCES_MAX => 100,
                    SubscriptionFeature::RESOURCE_CATEGORIES_MAX => 50,
                    SubscriptionFeature::STAFF_MAX => 20,
                    SubscriptionFeature::RESERVATIONS_MONTHLY_MAX => null,
                    SubscriptionFeature::ANALYTICS_BASIC => true,
                    SubscriptionFeature::ANALYTICS_ADVANCED => true,
                    SubscriptionFeature::PRICING_ADVANCED => true,
                    SubscriptionFeature::PROMO_CODES => true,
                    SubscriptionFeature::REVIEWS => true,
                ]),
            ],
            [
                'code' => 'business',
                'name' => 'Business',
                'description' => 'Higher limits for multi-location operators.',
                'translations' => [
                    'uz' => ['name' => 'Business', 'description' => 'Ko\'p filialli operatorlar uchun yuqori limitlar.'],
                    'ru' => ['name' => 'Бизнес', 'description' => 'Повышенные лимиты для сетевых операторов.'],
                    'kaa' => ['name' => 'Business', 'description' => 'Kóp filiallı operatorlar ushın joqarı limitler.'],
                ],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 40,
                'monthly_price' => 1990000,
                'yearly_price' => 19900000,
                'currency' => 'UZS',
                'trial_days' => 14,
                'entitlements' => $this->entitlements([
                    SubscriptionFeature::RESOURCES_MAX => 500,
                    SubscriptionFeature::RESOURCE_CATEGORIES_MAX => 100,
                    SubscriptionFeature::STAFF_MAX => 50,
                    SubscriptionFeature::RESERVATIONS_MONTHLY_MAX => null,
                    SubscriptionFeature::ANALYTICS_BASIC => true,
                    SubscriptionFeature::ANALYTICS_ADVANCED => true,
                    SubscriptionFeature::PRICING_ADVANCED => true,
                    SubscriptionFeature::PROMO_CODES => true,
                    SubscriptionFeature::REVIEWS => true,
                ]),
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'Custom limits and dedicated support.',
                'translations' => [
                    'uz' => ['name' => 'Enterprise', 'description' => 'Maxsus limitlar va maxsus qo\'llab-quvvatlash.'],
                    'ru' => ['name' => 'Enterprise', 'description' => 'Индивидуальные лимиты и выделенная поддержка.'],
                    'kaa' => ['name' => 'Enterprise', 'description' => 'Arnawlı limitler hám arnawlı qollap-quwatlaw.'],
                ],
                'is_active' => true,
                'is_public' => false,
                'sort_order' => 50,
                'monthly_price' => 0,
                'yearly_price' => 0,
                'currency' => 'UZS',
                'trial_days' => 0,
                'entitlements' => $this->entitlements([
                    SubscriptionFeature::RESOURCES_MAX => 10000,
                    SubscriptionFeature::RESOURCE_CATEGORIES_MAX => 1000,
                    SubscriptionFeature::STAFF_MAX => 500,
                    SubscriptionFeature::RESERVATIONS_MONTHLY_MAX => null,
                    SubscriptionFeature::ANALYTICS_BASIC => true,
                    SubscriptionFeature::ANALYTICS_ADVANCED => true,
                    SubscriptionFeature::PRICING_ADVANCED => true,
                    SubscriptionFeature::PROMO_CODES => true,
                    SubscriptionFeature::REVIEWS => true,
                ]),
            ],
        ];
    }

    /**
     * @param  array<string, bool|int|null>  $values
     * @return array<string, array{type: EntitlementValueType, value: bool|int|string}>
     */
    private function entitlements(array $values): array
    {
        $mapped = [];

        foreach ($values as $feature => $value) {
            if ($value === null) {
                continue;
            }

            $mapped[$feature] = [
                'type' => is_bool($value) ? EntitlementValueType::Boolean : EntitlementValueType::Integer,
                'value' => $value,
            ];
        }

        return $mapped;
    }
}
