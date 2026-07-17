<?php

namespace App\Filament\Pages;

use App\Models\ChatOrderLink;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Product;
use App\Services\Crm\ConversationMessengerService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class Inbox extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Inbox';

    protected string $view = 'filament.pages.inbox';

    public ?int $selectedConversationId = null;

    public string $replyBody = '';

    public string $statusFilter = 'open';

    public bool $showManualForm = false;

    public string $manualName = '';

    public string $manualPhone = '';

    public ?int $orderFormProductId = null;

    public int $orderFormQuantity = 1;

    public static function getNavigationBadge(): ?string
    {
        $unread = (int) Conversation::query()->sum('unread_count');

        return $unread > 0 ? (string) $unread : null;
    }

    public function getConversationsProperty(): Collection
    {
        return Conversation::query()
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->with(['lead', 'customer'])
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();
    }

    public function getSelectedConversationProperty(): ?Conversation
    {
        return $this->selectedConversationId
            ? Conversation::query()->with(['messages' => fn ($query) => $query->orderBy('sent_at'), 'lead', 'customer', 'channel'])->find($this->selectedConversationId)
            : null;
    }

    public function getProductsProperty(): Collection
    {
        return Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sale_price']);
    }

    public function selectConversation(int $conversationId): void
    {
        $this->selectedConversationId = $conversationId;
        $this->replyBody = '';
        Conversation::query()->find($conversationId)?->markRead();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = in_array($status, ['open', 'pending', 'closed', 'all'], true) ? $status : 'open';
    }

    public function setConversationStatus(string $status): void
    {
        if ($this->selectedConversation && array_key_exists($status, Conversation::STATUSES)) {
            $this->selectedConversation->update(['status' => $status]);
        }
    }

    public function sendReply(): void
    {
        $conversation = $this->selectedConversation;

        if (! $conversation || trim($this->replyBody) === '') {
            return;
        }

        try {
            $type = in_array($conversation->provider, ['whatsapp', 'messenger'], true) ? 'text' : 'note';
            app(ConversationMessengerService::class)->send($conversation, trim($this->replyBody), Auth::user(), $type);
            $this->replyBody = '';
        } catch (ValidationException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        } catch (\Throwable $exception) {
            Notification::make()->title('Message could not be sent: '.$exception->getMessage())->danger()->send();
        }
    }

    public function createManualConversation(): void
    {
        if (trim($this->manualName) === '' || trim($this->manualPhone) === '') {
            Notification::make()->title('Name and phone are required.')->warning()->send();

            return;
        }

        $conversation = Conversation::query()->create([
            'provider' => 'manual',
            'contact_name' => trim($this->manualName),
            'contact_phone' => trim($this->manualPhone),
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $this->manualName = '';
        $this->manualPhone = '';
        $this->showManualForm = false;
        $this->selectConversation($conversation->getKey());
    }

    public function sendOrderForm(): void
    {
        $conversation = $this->selectedConversation;
        $product = Product::query()->find($this->orderFormProductId);

        if (! $conversation || ! $product) {
            Notification::make()->title('Select a product first.')->warning()->send();

            return;
        }

        $link = ChatOrderLink::query()->create([
            'conversation_id' => $conversation->getKey(),
            'lead_id' => $conversation->lead_id,
            'prefill' => [
                'items' => [[
                    'product_id' => $product->getKey(),
                    'name' => $product->name,
                    'quantity' => max(1, $this->orderFormQuantity),
                    'unit_price' => (float) $product->sale_price,
                ]],
                'name' => $conversation->contact_name,
                'phone' => $conversation->contact_phone,
                'address' => $conversation->customer?->address,
            ],
            'created_by' => Auth::id(),
        ]);

        try {
            app(ConversationMessengerService::class)->send(
                $conversation,
                "অর্ডার করতে এই লিংকে ক্লিক করুন: {$link->publicUrl()}",
                Auth::user(),
                'order_form',
            );
            Notification::make()->title('Order link sent.')->success()->send();
        } catch (\Throwable $exception) {
            ConversationMessage::query()->create([
                'conversation_id' => $conversation->getKey(),
                'direction' => 'outgoing',
                'type' => 'order_form',
                'body' => "Order link: {$link->publicUrl()}",
                'delivery_status' => 'failed',
                'sent_by' => Auth::id(),
                'sent_at' => now(),
            ]);
            Notification::make()
                ->title('Link created, but could not be sent through the channel. Copy it from the thread and share manually.')
                ->warning()
                ->send();
        }

        $this->orderFormProductId = null;
        $this->orderFormQuantity = 1;
    }
}
