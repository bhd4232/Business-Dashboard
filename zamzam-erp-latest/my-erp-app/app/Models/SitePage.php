<?php

namespace App\Models;

use HasinHayder\TyroDashboard\Concerns\HasCrud;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SitePage extends Model
{
    use HasCrud;
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'seo_title',
        'seo_description',
        'og_image',
        'is_published',
        'sort_order',
    ];

    protected array $resourceRoles = ['super_admin', 'manager'];

    protected array $resourceReadonly = ['sales_staff', 'inventory_staff', 'accountant'];

    protected string $resourceTitle = 'Pages';

    protected string $resourceTitleSingular = 'Page';

    protected string $resourceUploadDirectory = 'website/pages';

    protected array $resourceFields = [
        'title' => ['type' => 'text', 'label' => 'Title', 'rules' => 'required|max:255', 'searchable' => true, 'sortable' => true],
        'slug' => ['type' => 'text', 'label' => 'Slug', 'rules' => 'required|max:255', 'searchable' => true, 'sortable' => true],
        'excerpt' => ['type' => 'textarea', 'label' => 'Excerpt', 'rules' => 'nullable', 'hide_in_index' => true],
        'content' => ['type' => 'richtext', 'label' => 'Content', 'rules' => 'nullable', 'hide_in_index' => true],
        'seo_title' => ['type' => 'text', 'label' => 'SEO Title', 'rules' => 'nullable|max:255', 'hide_in_index' => true],
        'seo_description' => ['type' => 'textarea', 'label' => 'SEO Description', 'rules' => 'nullable', 'hide_in_index' => true],
        'og_image' => [
            'type' => 'file',
            'label' => 'Open Graph Image',
            'hide_in_index' => true,
            'display_image' => true,
            'display_image_position' => 'top',
            'help_text' => 'Recommended: 1200x630 px for this page social sharing preview.',
        ],
        'is_published' => ['type' => 'boolean', 'label' => 'Published'],
        'sort_order' => ['type' => 'number', 'label' => 'Sort Order', 'rules' => 'nullable|integer|min:0', 'sortable' => true],
    ];
}
