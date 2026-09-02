<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthTokenResource extends JsonResource
{
    /**
     * @param  array{user: User, token: string}  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource['user'];

        $user->loadMissing([
            'businessMemberships.business:id,name',
        ]);
        $user->setRelation(
            'businessMemberships',
            $user->businessMemberships->where('status', \App\Domain\Businesses\Enums\BusinessMemberStatus::Active)->values()
        );

        return [
            'user' => new UserResource($user),
            'token' => $this->resource['token'],
            'token_type' => 'Bearer',
        ];
    }
}
