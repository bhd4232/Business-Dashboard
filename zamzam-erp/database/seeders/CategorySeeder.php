<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Electronics', 'slug' => 'electronics', 'sort_order' => 1,
             'children' => [
                 ['name' => 'Mobile & Accessories', 'slug' => 'mobile-accessories', 'sort_order' => 1],
                 ['name' => 'Computers', 'slug' => 'computers', 'sort_order' => 2],
                 ['name' => 'Audio & Video', 'slug' => 'audio-video', 'sort_order' => 3],
             ]],
            ['name' => 'Household', 'slug' => 'household', 'sort_order' => 2,
             'children' => [
                 ['name' => 'Kitchen', 'slug' => 'kitchen', 'sort_order' => 1],
                 ['name' => 'Furniture', 'slug' => 'furniture', 'sort_order' => 2],
                 ['name' => 'Decoration', 'slug' => 'decoration', 'sort_order' => 3],
             ]],
            ['name' => 'Clothing & Fashion', 'slug' => 'clothing-fashion', 'sort_order' => 3,
             'children' => [
                 ['name' => 'Men\'s Wear', 'slug' => 'mens-wear', 'sort_order' => 1],
                 ['name' => 'Women\'s Wear', 'slug' => 'womens-wear', 'sort_order' => 2],
                 ['name' => 'Kids Wear', 'slug' => 'kids-wear', 'sort_order' => 3],
             ]],
            ['name' => 'Cosmetics & Beauty', 'slug' => 'cosmetics-beauty', 'sort_order' => 4],
            ['name' => 'Sports & Outdoor', 'slug' => 'sports-outdoor', 'sort_order' => 5],
            ['name' => 'Toys & Games', 'slug' => 'toys-games', 'sort_order' => 6],
            ['name' => 'Stationery', 'slug' => 'stationery', 'sort_order' => 7],
            ['name' => 'Tools & Hardware', 'slug' => 'tools-hardware', 'sort_order' => 8],
            ['name' => 'General', 'slug' => 'general', 'sort_order' => 99],
        ];

        foreach ($categories as $cat) {
            $children = $cat['children'] ?? [];
            unset($cat['children']);

            $existing = DB::table('categories')->where('slug', $cat['slug'])->first();

            if (! $existing) {
                $parentId = DB::table('categories')->insertGetId(array_merge($cat, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            } else {
                $parentId = $existing->id;
            }

            foreach ($children as $child) {
                $childExists = DB::table('categories')->where('slug', $child['slug'])->exists();
                if (! $childExists) {
                    DB::table('categories')->insert(array_merge($child, [
                        'parent_id'  => $parentId,
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }
        }
    }
}
