<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Crm;
use App\Models\ChatOrderLink;
use App\Models\Conversation;
use App\Models\ConversationChannel;
use App\Models\ConversationMessage;
use App\Models\Product;
use App\Services\CompanyContext;
use App\Services\Crm\ConversationMessengerService;
use App\Support\CompanyMedia;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Throwable;

class Inbox extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $cluster = Crm::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Inbox';

    protected string $view = 'filament.pages.inbox';

    #[Url(as: 'conversation', history: true)]
    public ?int $selectedConversationId = null;

    #[Url(as: 'channel', history: true)]
    public ?int $channelId = null;

    #[Url(as: 'status')]
    public string $statusFilter = 'open';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'unread')]
    public bool $unreadOnly = false;

    #[Url(as: 'assigned')]
    public string $assignedFilter = 'all';

    public string $replyBody = '';

    public string $composerMode = 'reply';

    public int $messageLimit = 50;

    public bool $showManualForm = false;

    public string $manualName = '';

    public string $manualPhone = '';

    public ?int $orderFormProductId = null;

    public int $orderFormQuantity = 1;

    public string $productSearch = '';

    public bool $showCatalogPanel = false;

    public static function getNavigationBadge(): ?string
    {
        $unread = (int) Conversation::query()->sum('unread_count');

        return $unread > 0 ? (string) $unread : null;
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermission('crm.view') ?? false;
    }

    public function getCanManageConversationsProperty(): bool
    {
        return Auth::user()?->hasPermission('crm.manage') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if (! in_array($this->statusFilter, ['open', 'pending', 'closed', 'all'], true)) {
            $this->statusFilter = 'open';
        }

        if (! in_array($this->assignedFilter, ['all', 'mine', 'unassigned'], true)) {
            $this->assignedFilter = 'all';
        }

        if ($this->channelId !== null && ! ConversationChannel::query()->whereKey($this->channelId)->exists()) {
            $this->channelId = null;
        }

        if ($this->selectedConversationId !== null && ! Conversation::query()->whereKey($this->selectedConversationId)->exists()) {
            $this->selectedConversationId = null;
        }
    }

    public function getChannelsProperty(): Collection
    {
        return ConversationChannel::query()
            ->where('is_active', true)
            ->with('company')
            ->withSum('conversations as unread_total', 'unread_count')
            ->orderBy('provider')
            ->orderBy('display_name')
            ->get();
    }

    public function getAllUnreadCountProperty(): int
    {
        return (int) Conversation::query()->sum('unread_count');
    }

    public function getConversationsProperty(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Conversation::query()
            ->when($this->channelId !== null, fn (Builder $query): Builder => $query->where('channel_id', $this->channelId))
            ->when($this->statusFilter !== 'all', fn (Builder $query): Builder => $query->where('status', $this->statusFilter))
            ->when($this->unreadOnly, fn (Builder $query): Builder => $query->where('unread_count', '>', 0))
            ->when($this->assignedFilter === 'mine', fn (Builder $query): Builder => $query->where('assigned_to', Auth::id()))
            ->when($this->assignedFilter === 'unassigned', fn (Builder $query): Builder => $query->whereNull('assigned_to'))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('contact_name', 'like', "%{$search}%")
                        ->orWhere('contact_phone', 'like', "%{$search}%")
                        ->orWhere('external_contact_id', 'like', "%{$search}%");
                });
            })
            ->with(['company', 'lead', 'customer', 'channel.company', 'assignedUser', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(30, pageName: 'inboxPage');
    }

    public function getSelectedConversationProperty(): ?Conversation
    {
        if (! $this->selectedConversationId) {
            return null;
        }

        $conversation = Conversation::query()
            ->with(['company', 'lead', 'customer', 'channel.company', 'assignedUser'])
            ->find($this->selectedConversationId);

        if (! $conversation) {
            return null;
        }

        $limit = max(1, min($this->messageLimit, 500));
        $messages = $conversation->messages()
            ->with('sender')
            ->latest('sent_at')
            ->latest('id')
            ->limit($limit + 1)
            ->get();
        $hasOlderMessages = $messages->count() > $limit;
        $messages = $messages
            ->take($limit)
            ->sortBy(fn (ConversationMessage $message): string => $message->sent_at?->format('Y-m-d H:i:s.u').'-'.str_pad((string) $message->getKey(), 12, '0', STR_PAD_LEFT))
            ->values();

        return $conversation
            ->setRelation('hasOlderMessages', $hasOlderMessages)
            ->setRelation('messages', $messages);
    }

    public function getProductsProperty(): Collection
    {
        $conversation = $this->selectedConversation;

        if (! $this->showCatalogPanel || ! $conversation) {
            return collect();
        }

        return Product::withoutGlobalScopes()
            ->where('company_id', $conversation->company_id)
            ->where('is_active', true)
            ->when(trim($this->productSearch) !== '', fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->where('name', 'like', '%'.trim($this->productSearch).'%')
                    ->orWhere('sku', 'like', '%'.trim($this->productSearch).'%')))
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'company_id', 'name', 'sale_price', 'image']);
    }

    public function getSelectedProductProperty(): ?Product
    {
        $conversation = $this->selectedConversation;

        return ($this->orderFormProductId && $conversation)
            ? Product::withoutGlobalScopes()
                ->where('company_id', $conversation->company_id)
                ->whereKey($this->orderFormProductId)
                ->first()
            : null;
    }

    public function updatedSearch(): void
    {
        $this->resetPage(pageName: 'inboxPage');
    }

    public function updatedUnreadOnly(): void
    {
        $this->resetPage(pageName: 'inboxPage');
    }

    public function updatedAssignedFilter(): void
    {
        if (! in_array($this->assignedFilter, ['all', 'mine', 'unassigned'], true)) {
            $this->assignedFilter = 'all';
        }

        $this->resetPage(pageName: 'inboxPage');
    }

    /**
     * Browser Back/Forward updates URL-bound properties without calling
     * selectConversation(), so clear every contact-specific draft here too.
     */
    public function updatedSelectedConversationId(?int $conversationId): void
    {
        $conversation = $conversationId
            ? Conversation::query()->find($conversationId)
            : null;

        if ($conversationId && ! $conversation) {
            $this->selectedConversationId = null;
        }

        $this->resetConversationDraftState();
        $this->resetManualDraftState();

        if ($conversation) {
            $conversation->markRead();
            app(ConversationMessengerService::class)->dispatchLatestIncomingRead($conversation);
            $this->dispatch('inbox-conversation-selected');
            $this->dispatch('inbox-scroll-bottom');
        } else {
            $this->dispatch('inbox-list-focused');
        }

        $this->forgetInboxComputedProperties();
    }

    /** Keep channel history navigation from leaving a thread from another tab open. */
    public function updatedChannelId(?int $channelId): void
    {
        $normalizedChannelId = $channelId && ConversationChannel::query()
            ->whereKey($channelId)
            ->where('is_active', true)
            ->exists()
                ? $channelId
                : null;

        $this->channelId = $normalizedChannelId;

        if ($this->selectedConversationId && $normalizedChannelId !== null) {
            $selectionMatchesChannel = Conversation::query()
                ->whereKey($this->selectedConversationId)
                ->where('channel_id', $normalizedChannelId)
                ->exists();

            if (! $selectionMatchesChannel) {
                $this->selectedConversationId = null;
            }
        }

        $this->resetConversationDraftState();
        $this->resetManualDraftState();
        $this->resetPage(pageName: 'inboxPage');
        $this->forgetInboxComputedProperties();
    }

    public function setChannelFilter(?int $channelId): void
    {
        $this->channelId = $channelId && ConversationChannel::query()->whereKey($channelId)->where('is_active', true)->exists()
            ? $channelId
            : null;
        $this->selectedConversationId = null;
        $this->resetConversationDraftState();
        $this->resetManualDraftState();
        $this->forgetInboxComputedProperties();
        $this->resetPage(pageName: 'inboxPage');
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = in_array($status, ['open', 'pending', 'closed', 'all'], true) ? $status : 'open';
        $this->selectedConversationId = null;
        $this->resetConversationDraftState();
        $this->resetManualDraftState();
        $this->forgetInboxComputedProperties();
        $this->resetPage(pageName: 'inboxPage');
    }

    public function selectConversation(int $conversationId): void
    {
        $conversation = Conversation::query()->findOrFail($conversationId);

        $this->resetConversationDraftState();
        $this->resetManualDraftState();
        $this->selectedConversationId = (int) $conversation->getKey();
        $conversation->markRead();
        app(ConversationMessengerService::class)->dispatchLatestIncomingRead($conversation);
        $this->forgetInboxComputedProperties();
        $this->dispatch('inbox-conversation-selected');
        $this->dispatch('inbox-scroll-bottom');
    }

    public function deselectConversation(): void
    {
        $this->selectedConversationId = null;
        $this->resetConversationDraftState();
        $this->resetManualDraftState();
        $this->forgetInboxComputedProperties();
        $this->dispatch('inbox-list-focused');
    }

    public function refreshInbox(): void
    {
        $this->selectedConversation?->markRead();
        $this->forgetInboxComputedProperties();
    }

    public function loadOlderMessages(): void
    {
        $this->messageLimit = min(500, $this->messageLimit + 50);
        $this->forgetInboxComputedProperties();
        $this->dispatch('inbox-preserve-scroll');
    }

    public function setConversationStatus(string $status): void
    {
        $this->authorizeInboxManagement();
        $conversation = $this->selectedConversation;

        if ($conversation && array_key_exists($status, Conversation::STATUSES)) {
            $conversation->update(['status' => $status]);
            $this->statusFilter = $status;
            $this->forgetInboxComputedProperties();
        }
    }

    public function assignToMe(): void
    {
        $this->authorizeInboxManagement();
        $this->selectedConversation?->update(['assigned_to' => Auth::id()]);
        $this->forgetInboxComputedProperties();
    }

    public function unassignConversation(): void
    {
        $this->authorizeInboxManagement();
        $this->selectedConversation?->update(['assigned_to' => null]);
        $this->forgetInboxComputedProperties();
    }

    public function markConversationUnread(): void
    {
        $this->authorizeInboxManagement();
        $conversation = $this->selectedConversation;

        if (! $conversation) {
            return;
        }

        $conversation->forceFill(['unread_count' => max(1, $conversation->unread_count)])->saveQuietly();
        $this->forgetInboxComputedProperties();
        $this->deselectConversation();
    }

    public function toggleAi(): void
    {
        $this->authorizeInboxManagement();
        $conversation = $this->selectedConversation;

        if ($conversation) {
            $conversation->update(['ai_enabled' => ! $conversation->ai_enabled]);
            $this->forgetInboxComputedProperties();
        }
    }

    public function sendReply(): void
    {
        $this->authorizeInboxManagement();
        $conversation = $this->selectedConversation;

        if (! $conversation) {
            return;
        }

        $validated = $this->validate([
            'replyBody' => ['required', 'string', 'max:4096'],
            'composerMode' => ['required', 'in:reply,note'],
        ], [
            'replyBody.required' => 'Write a message before sending.',
            'replyBody.max' => 'Keep the message within 4,096 characters.',
        ]);
        $body = trim($validated['replyBody']);

        if ($body === '') {
            $this->addError('replyBody', 'Write a message before sending.');

            return;
        }

        try {
            if ($this->composerMode === 'note' || ! in_array($conversation->provider, ['whatsapp', 'messenger'], true)) {
                ConversationMessage::query()->create([
                    'conversation_id' => $conversation->getKey(),
                    'direction' => 'outgoing',
                    'type' => 'note',
                    'body' => $body,
                    'delivery_status' => 'internal',
                    'sent_by' => Auth::id(),
                    'generated_by' => 'human',
                    'sent_at' => now(),
                ]);
                $conversation->forceFill(['last_message_at' => now()])->saveQuietly();
            } else {
                $message = app(ConversationMessengerService::class)->send(
                    $conversation,
                    $body,
                    Auth::user(),
                    in_array($conversation->provider, ['whatsapp', 'messenger'], true) ? 'text' : 'note',
                );

                if ($message->delivery_status === 'failed') {
                    Notification::make()
                        ->title('Message saved, but the channel rejected it')
                        ->body((string) data_get($message->raw_payload, 'error.message', 'Check the channel connection, token, and reply window, then retry.'))
                        ->warning()
                        ->send();
                }
            }

            $this->replyBody = '';
            $this->resetValidation('replyBody');
            $this->dispatch('inbox-scroll-bottom');
        } catch (ValidationException $exception) {
            $this->addError('replyBody', collect($exception->errors())->flatten()->first() ?: 'The message could not be sent.');
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('replyBody', 'The message could not be sent. Test the channel connection and try again.');
        }

        $this->forgetInboxComputedProperties();
    }

    public function retryMessage(int $messageId): void
    {
        $this->authorizeInboxManagement();
        $conversation = $this->selectedConversation;

        if (! $conversation) {
            return;
        }

        $message = $conversation->messages()
            ->where('direction', 'outgoing')
            ->where('delivery_status', 'failed')
            ->findOrFail($messageId);

        try {
            $message = app(ConversationMessengerService::class)->retry($message, Auth::user());

            $notification = Notification::make()
                ->title($message->delivery_status === 'failed' ? 'Retry failed' : 'Message sent')
                ->body($message->delivery_status === 'failed'
                    ? (string) data_get($message->raw_payload, 'error.message', 'Test the channel connection and try again.')
                    : null);

            if ($message->delivery_status === 'failed') {
                $notification->danger();
            } else {
                $notification->success();
            }

            $notification->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Retry failed')->body('Test the channel connection and try again.')->danger()->send();
        }

        $this->forgetInboxComputedProperties();
    }

    public function createManualConversation(): void
    {
        $this->authorizeInboxManagement();
        $company = app(CompanyContext::class)->company();

        if (! $company) {
            Notification::make()->title('Select a company before logging a manual conversation.')->warning()->send();

            return;
        }

        $validated = $this->validate([
            'manualName' => ['required', 'string', 'max:120'],
            'manualPhone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\-\s]+$/'],
        ]);

        $conversation = Conversation::query()->create([
            'company_id' => $company->getKey(),
            'provider' => 'manual',
            'contact_name' => trim($validated['manualName']),
            'contact_phone' => trim($validated['manualPhone']),
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $this->manualName = '';
        $this->manualPhone = '';
        $this->showManualForm = false;
        $this->resetValidation();
        $this->selectConversation($conversation->getKey());
    }

    public function sendOrderForm(): void
    {
        $this->authorizeInboxManagement();
        $conversation = $this->selectedConversation;
        $product = $this->selectedProduct;

        if (! $conversation || ! $product) {
            $this->addError('orderFormProductId', 'Select a product first.');

            return;
        }

        $this->validate([
            'orderFormProductId' => ['required', 'integer'],
            'orderFormQuantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $imageUrl = CompanyMedia::publicUrl($product->image, $product);
        $link = ChatOrderLink::query()->create([
            'company_id' => $conversation->company_id,
            'conversation_id' => $conversation->getKey(),
            'lead_id' => $conversation->lead_id,
            'prefill' => [
                'items' => [[
                    'product_id' => $product->getKey(),
                    'name' => $product->name,
                    'quantity' => max(1, $this->orderFormQuantity),
                    'unit_price' => (float) $product->sale_price,
                    'image' => $product->image,
                ]],
                'name' => $conversation->contact_name,
                'phone' => $conversation->contact_phone,
                'address' => $conversation->customer?->address,
            ],
            'created_by' => Auth::id(),
        ]);

        $currency = $conversation->company?->currency ?: 'BDT';
        $body = "🛍️ {$product->name}\n"
            .$currency.' '.number_format((float) $product->sale_price, 2)."\n\n"
            ."অর্ডার করতে এই লিংকে ক্লিক করুন: {$link->publicUrl()}";

        try {
            if (in_array($conversation->provider, ['whatsapp', 'messenger'], true)) {
                $message = app(ConversationMessengerService::class)->send(
                    $conversation,
                    $body,
                    Auth::user(),
                    'order_form',
                    $imageUrl,
                );
            } else {
                $message = ConversationMessage::query()->create([
                    'conversation_id' => $conversation->getKey(),
                    'direction' => 'outgoing',
                    'type' => 'order_form',
                    'body' => $body,
                    'media_path' => $imageUrl,
                    'media_mime' => $imageUrl ? 'image/*' : null,
                    'delivery_status' => 'internal',
                    'sent_by' => Auth::id(),
                    'generated_by' => 'human',
                    'sent_at' => now(),
                ]);
                $conversation->forceFill(['last_message_at' => now()])->saveQuietly();
            }

            $notification = Notification::make()
                ->title(match ($message->delivery_status) {
                    'failed' => 'Order link saved, but delivery failed',
                    'internal' => 'Order link added as internal activity',
                    default => 'Order link sent',
                })
                ->body($message->delivery_status === 'failed'
                    ? (string) data_get($message->raw_payload, 'error.message', 'Copy the link from the thread or retry the message.')
                    : null);

            if ($message->delivery_status === 'failed') {
                $notification->warning();
            } else {
                $notification->success();
            }

            $notification->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->title('Order link created, but the channel could not send it')
                ->body('Copy the link from the thread after testing the channel connection.')
                ->warning()
                ->send();
        }

        $this->orderFormProductId = null;
        $this->orderFormQuantity = 1;
        $this->productSearch = '';
        $this->showCatalogPanel = false;
        $this->forgetInboxComputedProperties();
        $this->dispatch('inbox-scroll-bottom');
    }

    protected function authorizeInboxManagement(): void
    {
        abort_unless($this->canManageConversations, 403);
    }

    protected function resetConversationDraftState(): void
    {
        $this->replyBody = '';
        $this->composerMode = 'reply';
        $this->messageLimit = 50;
        $this->orderFormProductId = null;
        $this->orderFormQuantity = 1;
        $this->productSearch = '';
        $this->showCatalogPanel = false;
        $this->resetValidation();
    }

    protected function resetManualDraftState(): void
    {
        $this->showManualForm = false;
        $this->manualName = '';
        $this->manualPhone = '';
    }

    protected function forgetInboxComputedProperties(): void
    {
        unset(
            $this->selectedConversation,
            $this->conversations,
            $this->channels,
            $this->allUnreadCount,
            $this->products,
            $this->selectedProduct,
        );
    }
}
