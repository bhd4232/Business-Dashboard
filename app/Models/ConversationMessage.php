<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    public const TYPES = [
        'text' => 'Text',
        'image' => 'Image',
        'audio' => 'Audio',
        'video' => 'Video',
        'document' => 'Document',
        'sticker' => 'Sticker',
        'template' => 'Template',
        'order_form' => 'Order Form',
        'note' => 'Note',
    ];

    protected $fillable = [
        'conversation_id', 'direction', 'type', 'body', 'media_path', 'media_mime',
        'external_message_id', 'delivery_status', 'sent_by', 'generated_by',
        'ai_confidence', 'ai_meta', 'raw_payload', 'sent_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'ai_meta' => 'array',
        'ai_confidence' => 'decimal:3',
        'sent_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
