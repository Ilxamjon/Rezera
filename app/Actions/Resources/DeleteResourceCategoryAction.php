<?php

namespace App\Actions\Resources;

use App\Models\ResourceGroup;
use Illuminate\Validation\ValidationException;

class DeleteResourceCategoryAction
{
    public function execute(ResourceGroup $category): void
    {
        if ($category->activeResources()->exists()) {
            throw ValidationException::withMessages([
                'category' => [__('resources.category_has_resources')],
            ]);
        }

        $category->delete();
    }
}
