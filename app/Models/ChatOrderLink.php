<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatOrderLink extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'conversation_id', 'lead_id', 'quotation_id', 'token',
        'prefill', 'expires_at', 'converted_order_id', 'opened_at', 'created_by',
    ];

    protected $casts = [
        'prefill' => 'array',
        'expires_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChatOrderLink $link): void {
            $link->token ??= Str::random(40);
            $link->expires_at ??= now()->addDays(7);
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function isUsable(): bool
    {
        return $this->converted_order_id === null && $this->expires_at->isFuture();
    }

    public function publicUrl(): string
    {
        return route('chat-order.show', $this->token);
    }
}
