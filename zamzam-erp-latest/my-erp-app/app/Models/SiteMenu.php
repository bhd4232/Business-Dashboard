<?php

namespace App\Models;

use HasinHayder\TyroDashboard\Concerns\HasCrud;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteMenu extends Model
{
    use HasCrud;
    use HasFactory;

    protected $fillable = [
        'label',
        'url',
        'location',
        'sort_order',
        'is_active',
    ];

    protected array $resourceRoles = ['super_admin', 'manager'];

    protected array $resourceReadonly = ['sales_staff', 'inventory_staff', 'accountant'];

    protected string $resourceTitle = 'Menus';

    protected string $resourceTitleSingular = 'Menu';

    protected array $resourceFields = [
        'label' => ['type' => 'text', 'label' => 'Label', 'rules' => 'required|max:255', 'searchable' => true, 'sortable' => true],
        'url' => ['type' => 'text', 'label' => 'URL', 'rules' => 'required|max:255'],
        'location' => [
            'type' => 'select',
            'label' => 'Location',
            'rules' => 'required|in:header,footer',
            'options' => ['header' => 'Header', 'footer' => 'Footer'],
            'sortable' => true,
        ],
        'sort_order' => ['type' => 'number', 'label' => 'Sort Order', 'rules' => 'nullable|integer|min:0', 'sortable' => true],
        'is_active' => ['type' => 'boolean', 'label' => 'Active'],
    ];
}
