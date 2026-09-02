<?php

namespace App\Actions\Resources;

use App\Models\Resource;

class DeleteResourceAction
{
    public function execute(Resource $resource): void
    {
        $resource->delete();
    }
}
