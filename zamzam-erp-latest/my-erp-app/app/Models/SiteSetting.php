<?php

namespace App\Models;

use HasinHayder\TyroDashboard\Concerns\HasCrud;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasCrud;
    use HasFactory;

    protected $fillable = [
        'site_name',
        'tagline',
        'logo',
        'favicon',
        'header_show_site_name',
        'header_show_tagline',
        'header_logo_width',
        'header_logo_height',
        'phone',
        'email',
        'address',
        'footer_text',
        'facebook_url',
        'whatsapp_url',
        'seo_title',
        'seo_description',
        'og_image',
        'is_active',
    ];

    protected array $resourceRoles = ['super_admin', 'manager'];

    protected array $resourceReadonly = ['sales_staff', 'inventory_staff', 'accountant'];

    protected string $resourceTitle = 'Settings & SEO';

    protected string $resourceTitleSingular = 'Setting & SEO';

    protected string $resourceUploadDirectory = 'website';

    protected $casts = [
        'header_show_site_name' => 'boolean',
        'header_show_tagline' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected array $resourceFields = [
        'site_name' => ['type' => 'text', 'label' => 'Site Name', 'rules' => 'required|max:255', 'searchable' => true, 'sortable' => true],
        'tagline' => ['type' => 'text', 'label' => 'Tagline', 'rules' => 'nullable|max:255'],
        'logo' => [
            'type' => 'file',
            'label' => 'Logo',
            'hide_in_index' => true,
            'display_image' => true,
            'display_image_position' => 'top',
            'help_text' => 'Recommended: transparent PNG or SVG, around 320x120 px.',
        ],
        'favicon' => [
            'type' => 'file',
            'label' => 'Favicon',
            'hide_in_index' => true,
            'display_image' => true,
            'display_image_position' => 'top',
            'help_text' => 'Recommended: square PNG/ICO, 512x512 px.',
        ],
        'header_show_site_name' => [
            'type' => 'boolean',
            'label' => 'Show Site Name In Header',
            'help_text' => 'Turn on only if the logo needs text beside it.',
        ],
        'header_show_tagline' => [
            'type' => 'boolean',
            'label' => 'Show Tagline In Header',
            'help_text' => 'Keep off for wide logos or busy header layouts.',
        ],
        'header_logo_width' => [
            'type' => 'number',
            'label' => 'Header Logo Width',
            'rules' => 'required|integer|min:48|max:360',
            'help_text' => 'Width in pixels. Recommended: 160-220 for wide logos.',
        ],
        'header_logo_height' => [
            'type' => 'number',
            'label' => 'Header Logo Height',
            'rules' => 'required|integer|min:32|max:120',
            'help_text' => 'Height in pixels. Recommended: 56-72 for the current header.',
        ],
        'phone' => ['type' => 'text', 'label' => 'Phone', 'rules' => 'nullable|max:255'],
        'email' => ['type' => 'email', 'label' => 'Email', 'rules' => 'nullable|email|max:255'],
        'address' => ['type' => 'textarea', 'label' => 'Address', 'rules' => 'nullable', 'hide_in_index' => true],
        'footer_text' => ['type' => 'textarea', 'label' => 'Footer Text', 'rules' => 'nullable', 'hide_in_index' => true],
        'facebook_url' => ['type' => 'url', 'label' => 'Facebook URL', 'rules' => 'nullable|url|max:255', 'hide_in_index' => true],
        'whatsapp_url' => ['type' => 'url', 'label' => 'WhatsApp URL', 'rules' => 'nullable|url|max:255', 'hide_in_index' => true],
        'seo_title' => ['type' => 'text', 'label' => 'SEO Title', 'rules' => 'nullable|max:255', 'hide_in_index' => true],
        'seo_description' => ['type' => 'textarea', 'label' => 'SEO Description', 'rules' => 'nullable', 'hide_in_index' => true],
        'og_image' => [
            'type' => 'file',
            'label' => 'Open Graph Image',
            'hide_in_index' => true,
            'display_image' => true,
            'display_image_position' => 'top',
            'help_text' => 'Recommended: 1200x630 px for social sharing previews.',
        ],
        'is_active' => ['type' => 'boolean', 'label' => 'Active'],
    ];

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->latest('id')->first()
            ?? static::query()->latest('id')->first();
    }
}
