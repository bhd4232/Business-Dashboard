<?php

namespace App\Models;

use HasinHayder\TyroDashboard\Concerns\HasCrud;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteBanner extends Model
{
    use HasCrud;
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'image',
        'primary_button_label',
        'primary_button_url',
        'secondary_button_label',
        'secondary_button_url',
        'sort_order',
        'is_active',
    ];

    protected array $resourceRoles = ['super_admin', 'manager'];

    protected array $resourceReadonly = ['sales_staff', 'inventory_staff', 'accountant'];

    protected string $resourceTitle = 'Banners';

    protected string $resourceTitleSingular = 'Banner';

    protected string $resourceUploadDirectory = 'website/banners';

    protected array $resourceFields = [
        'title' => ['type' => 'text', 'label' => 'Title', 'rules' => 'required|max:255', 'searchable' => true, 'sortable' => true],
        'subtitle' => ['type' => 'text', 'label' => 'Subtitle', 'rules' => 'nullable|max:255'],
        'description' => ['type' => 'textarea', 'label' => 'Description', 'rules' => 'nullable', 'hide_in_index' => true],
        'image' => [
            'type' => 'file',
            'label' => 'Image',
            'hide_in_index' => true,
            'display_image' => true,
            'display_image_position' => 'top',
            'help_text' => 'Recommended: 1600x900 px or wider landscape image for the homepage hero.',
        ],
        'primary_button_label' => ['type' => 'text', 'label' => 'Primary Button Label', 'rules' => 'nullable|max:255', 'hide_in_index' => true],
        'primary_button_url' => ['type' => 'text', 'label' => 'Primary Button URL', 'rules' => 'nullable|max:255', 'hide_in_index' => true],
        'secondary_button_label' => ['type' => 'text', 'label' => 'Secondary Button Label', 'rules' => 'nullable|max:255', 'hide_in_index' => true],
        'secondary_button_url' => ['type' => 'text', 'label' => 'Secondary Button URL', 'rules' => 'nullable|max:255', 'hide_in_index' => true],
        'sort_order' => ['type' => 'number', 'label' => 'Sort Order', 'rules' => 'nullable|integer|min:0', 'sortable' => true],
        'is_active' => ['type' => 'boolean', 'label' => 'Active'],
    ];
}
