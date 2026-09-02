<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessCategory extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessCategoryFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'image_url',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class, 'category_id');
    }
}
