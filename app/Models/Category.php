<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'slug', 'description', 'is_active'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
