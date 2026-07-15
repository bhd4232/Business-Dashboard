<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'slug', 'description', 'image', 'is_active'];

    protected static function booted(): void
    {
        static::saved(fn (Category $category) => Cache::forget("storefront-home:{$category->company_id}"));
        static::deleted(fn (Category $category) => Cache::forget("storefront-home:{$category->company_id}"));
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
