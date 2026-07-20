<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StorefrontPage extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'title',
        'slug',
        'excerpt',
        'cover_image',
        'content',
        'meta_title',
        'meta_description',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (StorefrontPage $page): void {
            $page->slug = static::normalizeSlug($page->slug ?: $page->title);
            $page->sort_order ??= 0;
            $page->is_published ??= false;
        });

        static::saving(function (StorefrontPage $page): void {
            $page->slug = static::normalizeSlug($page->slug ?: $page->title);
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public static function normalizeSlug(?string $slug): string
    {
        return Str::of($slug ?: 'page')
            ->trim()
            ->slug()
            ->limit(120, '')
            ->toString() ?: 'page';
    }
}
