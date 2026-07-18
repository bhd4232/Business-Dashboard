<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;

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

    /**
     * Escaped message body with bare URLs turned into links that open in a
     * new tab, so order-form links are tappable straight from the thread.
     */
    public function bodyHtml(): HtmlString
    {
        $escaped = e((string) $this->body);

        $linked = preg_replace_callback(
            '~https?://[^\s<]+~u',
            fn (array $match): string => '<a href="'.$match[0].'" target="_blank" rel="noopener noreferrer">'.$match[0].'</a>',
            $escaped,
        );

        return new HtmlString($linked ?? $escaped);
    }

    /**
     * Resolves the attached media to a browser-displayable image URL, or null
     * when the message has no media or the media isn't an image. Catalog
     * messages store a full URL; downloaded webhook media stores a relative
     * path on the public disk.
     */
    public function mediaImageUrl(): ?string
    {
        if (blank($this->media_path)) {
            return null;
        }

        $isImage = str_starts_with((string) $this->media_mime, 'image')
            || in_array(strtolower(pathinfo(parse_url((string) $this->media_path, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true);

        if (! $isImage) {
            return null;
        }

        return str_starts_with((string) $this->media_path, 'http')
            ? $this->media_path
            : asset('storage/'.ltrim((string) $this->media_path, '/'));
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
