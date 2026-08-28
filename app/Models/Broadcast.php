<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends Model
{
    use BelongsToCompany;

    public const AUDIENCE_TYPES = [
        'leads' => 'Leads',
        'customers' => 'Customers',
        'both' => 'Leads & Customers',
    ];

    public const CHANNELS = [
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'both' => 'WhatsApp (SMS fallback)',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'queued' => 'Queued',
        'sending' => 'Sending',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'company_id', 'name', 'audience_type', 'lead_status_filter', 'lead_source_filter',
        'channel', 'whatsapp_channel_id', 'whatsapp_template_name', 'whatsapp_template_language',
        'sms_body', 'status', 'recipients_count', 'sent_count', 'failed_count',
        'created_by', 'queued_at', 'completed_at',
    ];

    protected $casts = [
        'lead_status_filter' => 'array',
        'lead_source_filter' => 'array',
        'recipients_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'queued_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function whatsappChannel(): BelongsTo
    {
        return $this->belongsTo(ConversationChannel::class, 'whatsapp_channel_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'failed', 'cancelled'], true);
    }
}
