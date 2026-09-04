<?php

namespace Database\Seeders;

use App\Models\BusinessCategory;
use Illuminate\Database\Seeder;

class BusinessCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'gaming_club',
                'name' => [
                    'uz' => 'Kompyuter klubi',
                    'kaa' => 'Kompyuter klubi',
                    'ru' => 'Компьютерный клуб',
                ],
                'icon' => 'gaming',
                'sort_order' => 1,
            ],
            [
                'slug' => 'playstation_club',
                'name' => [
                    'uz' => 'PlayStation klubi',
                    'kaa' => 'PlayStation klubi',
                    'ru' => 'PlayStation клуб',
                ],
                'icon' => 'console',
                'sort_order' => 2,
            ],
            [
                'slug' => 'restaurant',
                'name' => [
                    'uz' => 'Restoran',
                    'kaa' => 'Restoran',
                    'ru' => 'Ресторан',
                ],
                'icon' => 'restaurant',
                'sort_order' => 3,
            ],
            [
                'slug' => 'cafe',
                'name' => [
                    'uz' => 'Kafe',
                    'kaa' => 'Kafe',
                    'ru' => 'Кафе',
                ],
                'icon' => 'cafe',
                'sort_order' => 4,
            ],
            [
                'slug' => 'coworking',
                'name' => [
                    'uz' => 'Kovorking',
                    'kaa' => 'Kovorking',
                    'ru' => 'Коворкинг',
                ],
                'icon' => 'coworking',
                'sort_order' => 5,
            ],
            [
                'slug' => 'sports_facility',
                'name' => [
                    'uz' => 'Sport inshooti',
                    'kaa' => 'Sport inshooti',
                    'ru' => 'Спортивный объект',
                ],
                'icon' => 'sports',
                'sort_order' => 6,
            ],
        ];

        foreach ($categories as $category) {
            BusinessCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
