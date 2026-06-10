<?php

namespace App\Models;

use HasinHayder\TyroDashboard\Concerns\HasCrud;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSection extends Model
{
    use HasCrud;
    use HasFactory;

    protected $fillable = [
        'key',
        'section_type',
        'title',
        'subtitle',
        'body',
        'image',
        'button_label',
        'button_url',
        'placement',
        'layout',
        'sort_order',
        'is_active',
    ];

    protected array $resourceRoles = ['super_admin', 'manager'];

    protected array $resourceReadonly = ['sales_staff', 'inventory_staff', 'accountant'];

    protected string $resourceTitle = 'Sections';

    protected string $resourceTitleSingular = 'Section';

    protected string $resourceUploadDirectory = 'website/sections';

    protected array $resourceFields = [
        'key' => ['type' => 'text', 'label' => 'Key', 'rules' => 'required|max:255', 'searchable' => true, 'sortable' => true],
        'section_type' => [
            'type' => 'select',
            'label' => 'Section Type',
            'rules' => 'required|in:featured_categories,featured_products_placeholder,service_block,cta_contact,custom',
            'options' => [
                'featured_categories' => 'Featured Categories',
                'featured_products_placeholder' => 'Featured Products Placeholder',
                'service_block' => 'Service Block',
                'cta_contact' => 'CTA / Contact Block',
                'custom' => 'Custom',
            ],
            'sortable' => true,
        ],
        'title' => ['type' => 'text', 'label' => 'Title', 'rules' => 'required|max:255', 'searchable' => true, 'sortable' => true],
        'subtitle' => ['type' => 'text', 'label' => 'Subtitle', 'rules' => 'nullable|max:255', 'hide_in_index' => true],
        'body' => ['type' => 'richtext', 'label' => 'Body', 'rules' => 'nullable', 'hide_in_index' => true],
        'image' => [
            'type' => 'file',
            'label' => 'Image',
            'hide_in_index' => true,
            'display_image' => true,
            'display_image_position' => 'top',
            'help_text' => 'Recommended: 1200x800 px for section cards, or 1600x900 px for wide sections.',
        ],
        'button_label' => ['type' => 'text', 'label' => 'Button Label', 'rules' => 'nullable|max:255', 'hide_in_index' => true],
        'button_url' => ['type' => 'text', 'label' => 'Button URL', 'rules' => 'nullable|max:255', 'hide_in_index' => true],
        'placement' => [
            'type' => 'select',
            'label' => 'Placement',
            'rules' => 'required|in:home,about,footer',
            'options' => [
                'home' => 'Home',
                'about' => 'About',
                'footer' => 'Footer',
            ],
            'sortable' => true,
        ],
        'layout' => [
            'type' => 'select',
            'label' => 'Layout',
            'rules' => 'required|in:card,wide,feature',
            'options' => [
                'card' => 'Card',
                'wide' => 'Wide',
                'feature' => 'Feature',
            ],
        ],
        'sort_order' => ['type' => 'number', 'label' => 'Sort Order', 'rules' => 'nullable|integer|min:0', 'sortable' => true],
        'is_active' => ['type' => 'boolean', 'label' => 'Active'],
    ];
}
