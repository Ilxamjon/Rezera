<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Platform\Enums\PlatformPermission;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::UsersView);
        $q = trim($request->string('q')->toString());

        if ($q === '' || strlen($q) < 2) {
            return $this->success(['users' => [], 'businesses' => [], 'reservations' => [], 'payments' => []]);
        }

        $term = '%'.addcslashes($q, '%_\\').'%';

        return $this->success([
            'users' => User::query()->where('name', 'ilike', $term)->orWhere('phone', 'ilike', $term)->limit(5)->get(['id', 'name', 'phone']),
            'businesses' => Business::query()->where('name', 'ilike', $term)->limit(5)->get(['id', 'name', 'status']),
            'reservations' => Reservation::query()->where('reservation_number', 'ilike', $term)->limit(5)->get(['id', 'reservation_number', 'status']),
            'payments' => Payment::query()->where('payment_number', 'ilike', $term)->limit(5)->get(['id', 'payment_number', 'status']),
        ]);
    }
}
