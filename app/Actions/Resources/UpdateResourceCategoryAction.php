<?php

namespace App\Actions\Resources;

use App\Models\ResourceGroup;

class UpdateResourceCategoryAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ResourceGroup $category, array $data): ResourceGroup
    {
        $category->fill($data);
        $category->save();

        return $category->fresh();
    }
}
