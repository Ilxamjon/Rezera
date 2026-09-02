<?php

namespace App\Actions\Pricing;

use App\Models\PricingRule;

final class DeletePricingRuleAction
{
    public function execute(PricingRule $rule): void
    {
        $rule->delete();
    }
}
