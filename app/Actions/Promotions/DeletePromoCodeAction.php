<?php

namespace App\Actions\Promotions;

use App\Models\PromoCode;

final class DeletePromoCodeAction
{
    public function execute(PromoCode $promo): void
    {
        $promo->delete();
    }
}
