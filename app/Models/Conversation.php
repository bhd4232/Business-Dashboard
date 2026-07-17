<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToCompany;

    public const PROVIDERS = [
        'whatsapp' => 'WhatsApp',
        'messenger' => 'Messenger',
        'phone' => 'Phone',
        'manual' => 'Manual',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'pending' => 'Pending',
        'closed' => 'Closed',
    ];

    protected $fillable = [
        'company_id', 'channel_id', 'provider', 'entry_point', 'ad_referral_id',
        'external_contact_id', 'contact_name', 'contact_phone', 'lead_id', 'customer_id',
        'status', 'ai_enabled', 'human_handled_until', 'assigned_to',
        'last_message_at', 'unread_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'unread_count' => 'integer',
        'ai_enabled' => 'boolean',
        'human_handled_until' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(ConversationChannel::class, 'channel_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function markRead(): void
    {
        if ($this->unread_count > 0) {
            $this->forceFill(['unread_count' => 0])->saveQuietly();
        }
    }

    /**
     * WhatsApp/Messenger allow free-form replies only within 24 hours of the
     * customer's last incoming message; outside that window only approved
     * template messages can be sent. Conversations opened from a Click-to-
     * WhatsApp ad (CTWA Free Entry Point) get 72 hours instead.
     */
    public function withinReplyWindow(): bool
    {
        if (! in_array($this->provider, ['whatsapp', 'messenger'], true)) {
            return true;
        }

        $lastIncomingAt = $this->messages()
            ->where('direction', 'incoming')
            ->latest('sent_at')
            ->value('sent_at');

        if ($lastIncomingAt === null) {
            return false;
        }

        return now()->diffInHours($lastIncomingAt, true) < $this->replyWindowHours();
    }

    public function replyWindowHours(): int
    {
        return $this->entry_point === 'ctwa_ad' ? 72 : 24;
    }

    /** Whole hours left in the messaging window, null when it is closed. */
    public function replyWindowHoursLeft(): ?int
    {
        if (! in_array($this->provider, ['whatsapp', 'messenger'], true)) {
            return null;
        }

        $lastIncomingAt = $this->messages()
            ->where('direction', 'incoming')
            ->latest('sent_at')
            ->value('sent_at');

        if ($lastIncomingAt === null) {
            return null;
        }

        $left = $this->replyWindowHours() - now()->diffInHours($lastIncomingAt, true);

        return $left > 0 ? (int) floor($left) : null;
    }
}
