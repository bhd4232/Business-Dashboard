<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ExpenseCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Create an active category, suffixing the slug (-2, -3, …) when the
     * company already has one with the same slug.
     */
    public static function createWithUniqueSlug(string $name, ?string $description = null): self
    {
        $slug = Str::slug($name) ?: 'category';
        $originalSlug = $slug;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$suffix}";
            $suffix++;
        }

        return static::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'is_active' => true,
        ]);
    }
}
