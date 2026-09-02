<?php

namespace App\Actions\Resources;

use App\Models\Resource;

class UpdateResourceAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Resource $resource, array $data): Resource
    {
        $resource->fill($data);
        $resource->save();

        return $resource->fresh(['group']);
    }
}
