<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use BelongsToCompany;

    public const SOURCES = [
        'facebook' => 'Facebook',
        'whatsapp' => 'WhatsApp',
        'website' => 'Website',
        'referral' => 'Referral',
        'walk_in' => 'Walk-in',
        'phone_call' => 'Phone Call',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'quoted' => 'Quoted',
        'won' => 'Won',
        'lost' => 'Lost',
    ];

    protected $fillable = [
        'company_id', 'name', 'phone', 'email', 'source', 'status',
        'interest', 'estimated_value', 'assigned_to', 'next_follow_up_at',
        'converted_customer_id', 'converted_order_id', 'note', 'created_by',
    ];

    protected $casts = [
        'next_follow_up_at' => 'datetime',
        'estimated_value' => 'decimal:2',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function isConverted(): bool
    {
        return $this->status === 'won' && $this->converted_customer_id !== null;
    }
}
