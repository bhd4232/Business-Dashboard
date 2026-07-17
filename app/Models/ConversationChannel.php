<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationChannel extends Model
{
    use BelongsToCompany;

    public const PROVIDERS = [
        'whatsapp' => 'WhatsApp Cloud API',
        'messenger' => 'Facebook Messenger',
    ];

    protected $fillable = [
        'company_id', 'provider', 'external_id', 'display_name',
        'access_token', 'app_secret', 'verify_token',
        'auto_create_leads', 'is_active',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'app_secret' => 'encrypted',
        'auto_create_leads' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'channel_id');
    }

    public function verifySignature(string $payload, ?string $signatureHeader): bool
    {
        if (blank($this->app_secret) || blank($signatureHeader)) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $payload, $this->app_secret);

        return hash_equals($expected, $signatureHeader);
    }
}
