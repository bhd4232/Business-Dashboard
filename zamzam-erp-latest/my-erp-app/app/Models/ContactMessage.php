<?php

namespace App\Models;

use HasinHayder\TyroDashboard\Concerns\HasCrud;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasCrud;
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'admin_notes',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    protected array $resourceRoles = ['super_admin', 'manager'];

    protected array $resourceReadonly = ['sales_staff', 'accountant'];

    protected string $resourceTitle = 'Contact Messages';

    protected string $resourceTitleSingular = 'Contact Message';

    protected array $resourceFields = [
        'name' => ['type' => 'text', 'label' => 'Name', 'rules' => 'required|max:255', 'searchable' => true, 'sortable' => true],
        'email' => ['type' => 'email', 'label' => 'Email', 'rules' => 'required|email|max:255', 'searchable' => true, 'sortable' => true],
        'phone' => ['type' => 'text', 'label' => 'Phone', 'rules' => 'nullable|max:255', 'searchable' => true],
        'subject' => ['type' => 'text', 'label' => 'Subject', 'rules' => 'nullable|max:255', 'searchable' => true],
        'message' => ['type' => 'textarea', 'label' => 'Message', 'rules' => 'required', 'hide_in_index' => true],
        'status' => [
            'type' => 'select',
            'label' => 'Status',
            'rules' => 'required|in:new,read,responded,archived',
            'options' => [
                'new' => 'New',
                'read' => 'Read',
                'responded' => 'Responded',
                'archived' => 'Archived',
            ],
            'sortable' => true,
        ],
        'admin_notes' => ['type' => 'textarea', 'label' => 'Admin Notes', 'rules' => 'nullable', 'hide_in_index' => true],
        'responded_at' => ['type' => 'datetime-local', 'label' => 'Responded At', 'rules' => 'nullable|date'],
    ];
}
