<x-filament-panels::page>
    <style>
        .zz-inbox { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 1024px) { .zz-inbox { grid-template-columns: 340px minmax(0, 1fr); } }

        .zz-panel {
            background: #fff;
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px rgba(0,0,0,.05);
            border: 1px solid rgba(3,7,18,.08);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .dark .zz-panel { background: rgb(24 24 27); border-color: rgba(255,255,255,.1); }

        .zz-panel-header {
            padding: .75rem 1rem;
            border-bottom: 1px solid rgba(3,7,18,.08);
            display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
        }
        .dark .zz-panel-header { border-color: rgba(255,255,255,.1); }

        .zz-filter {
            padding: .3rem .75rem; font-size: .75rem; font-weight: 600;
            border-radius: 9999px; cursor: pointer;
            background: rgba(3,7,18,.05); color: rgb(63 63 70);
            border: none; transition: background .15s;
        }
        .dark .zz-filter { background: rgba(255,255,255,.08); color: rgb(212 212 216); }
        .zz-filter:hover { background: rgba(3,7,18,.1); }
        .dark .zz-filter:hover { background: rgba(255,255,255,.15); }
        .zz-filter.active { background: rgb(217 119 6); color: #fff; }
        .dark .zz-filter.active { background: rgb(245 158 11); color: rgb(24 24 27); }

        .zz-conv-list { overflow-y: auto; max-height: 68vh; flex: 1; }
        .zz-conv {
            width: 100%; text-align: left; padding: .75rem 1rem; cursor: pointer;
            border: none; background: transparent; display: block;
            border-bottom: 1px solid rgba(3,7,18,.05);
        }
        .dark .zz-conv { border-color: rgba(255,255,255,.06); }
        .zz-conv:hover { background: rgba(3,7,18,.03); }
        .dark .zz-conv:hover { background: rgba(255,255,255,.05); }
        .zz-conv.selected { background: rgba(217,119,6,.08); box-shadow: inset 3px 0 0 rgb(217 119 6); }
        .dark .zz-conv.selected { background: rgba(245,158,11,.1); box-shadow: inset 3px 0 0 rgb(245 158 11); }

        .zz-conv-name { font-weight: 600; font-size: .875rem; color: rgb(24 24 27); display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
        .dark .zz-conv-name { color: #fff; }
        .zz-conv-meta { margin-top: .2rem; font-size: .72rem; color: rgb(113 113 122); display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }

        .zz-thread { display: flex; flex-direction: column; max-height: 78vh; min-height: 24rem; }
        .zz-messages { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: .5rem; }

        .zz-bubble-row { display: flex; }
        .zz-bubble-row.out { justify-content: flex-end; }
        .zz-bubble {
            max-width: 75%; padding: .5rem .8rem; border-radius: 1rem; font-size: .875rem;
            background: rgba(3,7,18,.06); color: rgb(24 24 27);
            border-bottom-left-radius: .25rem;
        }
        .dark .zz-bubble { background: rgba(255,255,255,.09); color: rgb(244 244 245); }
        .zz-bubble-row.out .zz-bubble {
            background: rgb(217 119 6); color: #fff;
            border-bottom-left-radius: 1rem; border-bottom-right-radius: .25rem;
        }
        .dark .zz-bubble-row.out .zz-bubble { background: rgb(180 83 9); }
        .zz-bubble p.body { white-space: pre-wrap; overflow-wrap: anywhere; margin: 0; }
        .zz-bubble .stamp { font-size: .65rem; opacity: .65; margin-top: .25rem; }
        .zz-bubble .kind { font-size: .62rem; text-transform: uppercase; letter-spacing: .05em; opacity: .7; margin-bottom: .15rem; }

        .zz-composer { border-top: 1px solid rgba(3,7,18,.08); padding: .75rem 1rem; display: flex; flex-direction: column; gap: .6rem; }
        .dark .zz-composer { border-color: rgba(255,255,255,.1); }
        .zz-row { display: flex; gap: .5rem; align-items: flex-end; }
        .zz-row .grow { flex: 1; min-width: 0; }
        .zz-qty { width: 5.5rem; }

        .zz-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 3rem 1rem; color: rgb(113 113 122); font-size: .875rem; }
        .zz-manual-form { padding: .75rem 1rem; border-bottom: 1px solid rgba(3,7,18,.08); display: flex; flex-direction: column; gap: .5rem; }
        .dark .zz-manual-form { border-color: rgba(255,255,255,.1); }
    </style>

    <div class="zz-inbox" wire:poll.10s>
        {{-- Conversation list --}}
        <div class="zz-panel">
            <div class="zz-panel-header">
                @foreach (['open' => 'Open', 'pending' => 'Pending', 'closed' => 'Closed', 'all' => 'All'] as $key => $label)
                    <button type="button" wire:click="setStatusFilter('{{ $key }}')" class="zz-filter {{ $statusFilter === $key ? 'active' : '' }}">
                        {{ $label }}
                    </button>
                @endforeach
                <div style="margin-left: auto;">
                    <x-filament::icon-button
                        icon="heroicon-o-plus"
                        wire:click="$toggle('showManualForm')"
                        label="Log a manual conversation"
                        color="gray"
                    />
                </div>
            </div>

            @if ($showManualForm)
                <div class="zz-manual-form">
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="manualName" placeholder="Contact name" />
                    </x-filament::input.wrapper>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="manualPhone" placeholder="Phone" />
                    </x-filament::input.wrapper>
                    <x-filament::button wire:click="createManualConversation" icon="heroicon-m-chat-bubble-left-ellipsis" size="sm">
                        Create conversation
                    </x-filament::button>
                </div>
            @endif

            <div class="zz-conv-list">
                @forelse ($this->conversations as $conversation)
                    <button
                        type="button"
                        wire:click="selectConversation({{ $conversation->getKey() }})"
                        wire:key="conv-{{ $conversation->getKey() }}"
                        class="zz-conv {{ $selectedConversationId === $conversation->getKey() ? 'selected' : '' }}"
                    >
                        <span class="zz-conv-name">
                            <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $conversation->contact_name ?: $conversation->contact_phone ?: 'Contact '.$conversation->external_contact_id }}
                            </span>
                            @if ($conversation->unread_count > 0)
                                <x-filament::badge color="danger" size="sm">{{ $conversation->unread_count }}</x-filament::badge>
                            @endif
                        </span>
                        <span class="zz-conv-meta">
                            <x-filament::badge color="gray" size="sm">{{ \App\Models\Conversation::PROVIDERS[$conversation->provider] ?? $conversation->provider }}</x-filament::badge>
                            @if ($conversation->customer)
                                <x-filament::badge color="success" size="sm">Customer</x-filament::badge>
                            @elseif ($conversation->lead)
                                <x-filament::badge color="info" size="sm">Lead</x-filament::badge>
                            @endif
                            <span>{{ $conversation->last_message_at?->diffForHumans() }}</span>
                        </span>
                    </button>
                @empty
                    <div class="zz-empty">No conversations yet.</div>
                @endforelse
            </div>
        </div>

        {{-- Thread --}}
        <div class="zz-panel zz-thread">
            @if ($this->selectedConversation)
                @php($conversation = $this->selectedConversation)
                <div class="zz-panel-header">
                    <div style="min-width: 0;">
                        <p style="font-weight: 600; font-size: .9rem; margin: 0;">
                            {{ $conversation->contact_name ?: $conversation->contact_phone ?: 'Contact' }}
                        </p>
                        <p style="font-size: .72rem; margin: .15rem 0 0; color: rgb(113 113 122);">
                            {{ \App\Models\Conversation::PROVIDERS[$conversation->provider] ?? $conversation->provider }}
                            @if ($conversation->contact_phone) · {{ $conversation->contact_phone }} @endif
                            @if ($conversation->lead) · Lead: {{ $conversation->lead->name }} @endif
                            @if ($conversation->customer) · Customer: {{ $conversation->customer->name }} @endif
                        </p>
                    </div>
                    <div style="margin-left: auto; display: flex; align-items: center; gap: .4rem; flex-wrap: wrap;">
                        @if (in_array($conversation->provider, ['whatsapp', 'messenger'], true))
                            @php($hoursLeft = $conversation->replyWindowHoursLeft())
                            <x-filament::badge :color="$hoursLeft !== null ? 'success' : 'danger'" size="sm">
                                @if ($hoursLeft !== null)
                                    {{ $conversation->replyWindowHours() }}h window · {{ $hoursLeft }}h left
                                    @if ($conversation->entry_point === 'ctwa_ad') · Ad @endif
                                @else
                                    Window closed
                                @endif
                            </x-filament::badge>
                        @endif
                        @if ($conversation->status === 'pending')
                            <x-filament::badge color="warning" size="sm">🤖→👤 needs review</x-filament::badge>
                        @endif
                        @foreach (['open' => 'Open', 'pending' => 'Pending', 'closed' => 'Close'] as $key => $label)
                            @if ($conversation->status !== $key)
                                <x-filament::button wire:click="setConversationStatus('{{ $key }}')" color="gray" size="xs" outlined>
                                    {{ $label }}
                                </x-filament::button>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="zz-messages">
                    @forelse ($conversation->messages as $message)
                        <div class="zz-bubble-row {{ $message->direction === 'outgoing' ? 'out' : '' }}" wire:key="msg-{{ $message->getKey() }}">
                            <div class="zz-bubble">
                                @if (! in_array($message->type, ['text', 'note'], true))
                                    <p class="kind">{{ \App\Models\ConversationMessage::TYPES[$message->type] ?? $message->type }}</p>
                                @endif
                                @if ($message->media_path)
                                    <p class="kind">📎 {{ basename($message->media_path) }}</p>
                                @endif
                                <p class="body">{{ $message->body }}</p>
                                <p class="stamp">
                                    {{ $message->sent_at->format('d M, H:i') }}
                                    @if ($message->direction === 'outgoing') · {{ $message->delivery_status }} @endif
                                    @if ($message->generated_by === 'ai') · 🤖 AI
                                    @elseif ($message->sender) · {{ $message->sender->name }} @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="zz-empty">No messages in this conversation yet.</div>
                    @endforelse
                </div>

                <div class="zz-composer">
                    <div class="zz-row">
                        <div class="grow">
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model="orderFormProductId">
                                    <option value="">Send order form: choose product…</option>
                                    @foreach ($this->products as $product)
                                        <option value="{{ $product->getKey() }}">{{ $product->name }} (৳{{ number_format((float) $product->sale_price, 2) }})</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>
                        <div class="zz-qty">
                            <x-filament::input.wrapper>
                                <x-filament::input type="number" min="1" wire:model="orderFormQuantity" />
                            </x-filament::input.wrapper>
                        </div>
                        <x-filament::button wire:click="sendOrderForm" color="success" icon="heroicon-m-shopping-cart">
                            Send link
                        </x-filament::button>
                    </div>
                    <div class="zz-row">
                        <div class="grow">
                            <x-filament::input.wrapper>
                                <textarea
                                    wire:model="replyBody"
                                    rows="2"
                                    placeholder="{{ in_array($conversation->provider, ['whatsapp', 'messenger'], true) ? 'Write a reply…' : 'Add an internal note…' }}"
                                    class="fi-input block w-full border-none bg-transparent"
                                    style="width: 100%; border: none; background: transparent; outline: none; resize: vertical; padding: .5rem .75rem; font-size: .875rem; color: inherit;"
                                ></textarea>
                            </x-filament::input.wrapper>
                        </div>
                        <x-filament::button wire:click="sendReply" icon="heroicon-m-paper-airplane">
                            Send
                        </x-filament::button>
                    </div>
                </div>
            @else
                <div class="zz-empty">Select a conversation to view the thread.</div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
