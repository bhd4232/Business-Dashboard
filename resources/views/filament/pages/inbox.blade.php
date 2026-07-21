<x-filament-panels::page>
    <style>
        .zz-inbox { display: grid; grid-template-columns: 1fr; gap: 0; border-radius: .9rem; overflow: hidden; border: 1px solid rgba(3,7,18,.08); box-shadow: 0 1px 2px rgba(0,0,0,.05); height: calc(100dvh - 11.5rem); min-height: 26rem; }
        .dark .zz-inbox { border-color: rgba(255,255,255,.1); }
        @media (min-width: 1024px) { .zz-inbox { grid-template-columns: 360px minmax(0, 1fr); } }

        .zz-panel { background: #fff; overflow: hidden; display: flex; flex-direction: column; min-height: 0; }
        .dark .zz-panel { background: rgb(24 24 27); }
        .zz-list-panel { border-right: 1px solid rgba(3,7,18,.08); }
        .dark .zz-list-panel { border-color: rgba(255,255,255,.1); }

        /* WhatsApp-style mobile navigation: list OR thread, never both. */
        @media (max-width: 1023px) {
            .zz-inbox.has-sel .zz-list-panel { display: none; }
            .zz-inbox:not(.has-sel) .zz-thread { display: none; }
        }
        .zz-back { display: none; }
        @media (max-width: 1023px) { .zz-back { display: inline-flex; } }

        .zz-panel-header { padding: .6rem .9rem; border-bottom: 1px solid rgba(3,7,18,.08); display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; background: rgba(3,7,18,.02); }
        .dark .zz-panel-header { border-color: rgba(255,255,255,.1); background: rgba(255,255,255,.03); }

        .zz-filter { padding: .3rem .75rem; font-size: .75rem; font-weight: 600; border-radius: 9999px; cursor: pointer; background: rgba(3,7,18,.05); color: rgb(63 63 70); border: none; transition: background .15s; }
        .dark .zz-filter { background: rgba(255,255,255,.08); color: rgb(212 212 216); }
        .zz-filter:hover { background: rgba(3,7,18,.1); }
        .dark .zz-filter:hover { background: rgba(255,255,255,.15); }
        .zz-filter.active { background: rgb(217 119 6); color: #fff; }
        .dark .zz-filter.active { background: rgb(245 158 11); color: rgb(24 24 27); }

        .zz-conv-list { overflow-y: auto; flex: 1; min-height: 0; }
        .zz-conv { width: 100%; text-align: left; padding: .65rem .9rem; cursor: pointer; border: none; background: transparent; display: flex; gap: .7rem; align-items: center; border-bottom: 1px solid rgba(3,7,18,.05); }
        .dark .zz-conv { border-color: rgba(255,255,255,.06); }
        .zz-conv:hover { background: rgba(3,7,18,.03); }
        .dark .zz-conv:hover { background: rgba(255,255,255,.05); }
        .zz-conv.selected { background: rgba(217,119,6,.08); box-shadow: inset 3px 0 0 rgb(217 119 6); }
        .dark .zz-conv.selected { background: rgba(245,158,11,.1); box-shadow: inset 3px 0 0 rgb(245 158 11); }

        .zz-avatar { flex: none; width: 2.6rem; height: 2.6rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; color: #fff; background: linear-gradient(135deg, rgb(217 119 6), rgb(245 158 11)); }
        .zz-conv-main { min-width: 0; flex: 1; }
        .zz-conv-top { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; }
        .zz-conv-name { font-weight: 600; font-size: .875rem; color: rgb(24 24 27); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dark .zz-conv-name { color: #fff; }
        .zz-conv-time { flex: none; font-size: .68rem; color: rgb(113 113 122); }
        .zz-conv-bottom { display: flex; justify-content: space-between; align-items: center; gap: .5rem; margin-top: .15rem; }
        .zz-conv-preview { font-size: .78rem; color: rgb(113 113 122); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
        .zz-unread { flex: none; min-width: 1.25rem; height: 1.25rem; padding: 0 .3rem; border-radius: 9999px; background: rgb(22 163 74); color: #fff; font-size: .68rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
        .zz-conv-tags { display: flex; gap: .3rem; margin-top: .2rem; flex-wrap: wrap; }

        .zz-thread { display: flex; flex-direction: column; min-height: 0; }
        .zz-messages {
            flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: .35rem; min-height: 0;
            background-color: #efe7dd;
            background-image: radial-gradient(rgba(3,7,18,.045) 1px, transparent 1.4px);
            background-size: 22px 22px;
        }
        .dark .zz-messages { background-color: rgb(16 16 20); background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1.4px); }

        .zz-day { align-self: center; margin: .5rem 0 .3rem; font-size: .68rem; font-weight: 600; padding: .25rem .7rem; border-radius: .5rem; background: rgba(255,255,255,.85); color: rgb(82 82 91); box-shadow: 0 1px 1px rgba(0,0,0,.06); }
        .dark .zz-day { background: rgba(255,255,255,.08); color: rgb(161 161 170); }

        .zz-bubble-row { display: flex; }
        .zz-bubble-row.out { justify-content: flex-end; }
        .zz-bubble { max-width: 78%; padding: .45rem .7rem; border-radius: .75rem; font-size: .875rem; background: #fff; color: rgb(24 24 27); box-shadow: 0 1px 1px rgba(0,0,0,.08); border-top-left-radius: .2rem; }
        .dark .zz-bubble { background: rgb(39 39 46); color: rgb(244 244 245); }
        .zz-bubble-row.out .zz-bubble { background: #fff7e6; border-top-left-radius: .75rem; border-top-right-radius: .2rem; }
        .dark .zz-bubble-row.out .zz-bubble { background: rgb(69 44 10); }
        .zz-bubble p.body { white-space: pre-wrap; overflow-wrap: anywhere; margin: 0; }
        .zz-bubble p.body a { color: rgb(180 83 9); font-weight: 600; text-decoration: underline; text-underline-offset: 2px; word-break: break-all; }
        .dark .zz-bubble p.body a { color: rgb(252 211 77); }
        .zz-bubble .stamp { font-size: .62rem; opacity: .6; margin-top: .2rem; text-align: right; display: flex; justify-content: flex-end; gap: .25rem; }
        .zz-bubble .kind { font-size: .6rem; text-transform: uppercase; letter-spacing: .05em; opacity: .65; margin-bottom: .1rem; }
        .zz-bubble img.media { display: block; max-width: 100%; width: 16rem; border-radius: .55rem; margin-bottom: .35rem; }
        .zz-tick { font-weight: 700; }
        .zz-tick.failed { color: rgb(220 38 38); opacity: 1; }

        .zz-composer { border-top: 1px solid rgba(3,7,18,.08); padding: .55rem .75rem; display: flex; flex-direction: column; gap: .55rem; background: rgba(3,7,18,.02); }
        .dark .zz-composer { border-color: rgba(255,255,255,.1); background: rgba(255,255,255,.03); }
        .zz-row { display: flex; gap: .5rem; align-items: flex-end; }
        .zz-row .grow { flex: 1; min-width: 0; }
        .zz-qty { width: 5.5rem; }

        .zz-pill { flex: 1; min-width: 0; display: flex; align-items: center; background: #fff; border: 1px solid rgba(3,7,18,.1); border-radius: 1.4rem; padding: .15rem .35rem .15rem .9rem; }
        .dark .zz-pill { background: rgb(39 39 46); border-color: rgba(255,255,255,.12); }
        .zz-pill textarea { flex: 1; min-width: 0; border: none; background: transparent; outline: none; resize: none; padding: .5rem 0; font-size: .875rem; color: inherit; max-height: 7rem; box-shadow: none; }
        .zz-pill textarea:focus { box-shadow: none; outline: none; }
        .zz-round-btn { flex: none; width: 2.6rem; height: 2.6rem; border-radius: 50%; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; background: rgb(217 119 6); color: #fff; transition: transform .1s, background .15s; }
        .zz-round-btn:hover { background: rgb(180 83 9); }
        .zz-round-btn:active { transform: scale(.93); }
        .zz-round-btn svg { width: 1.25rem; height: 1.25rem; }
        .zz-round-btn.ghost { background: rgba(3,7,18,.06); color: rgb(82 82 91); }
        .dark .zz-round-btn.ghost { background: rgba(255,255,255,.1); color: rgb(212 212 216); }
        .zz-round-btn.ghost.on { background: rgb(22 163 74); color: #fff; transform: rotate(45deg); }

        .zz-catalog { border: 1px solid rgba(3,7,18,.08); border-radius: .75rem; background: #fff; padding: .6rem; display: flex; flex-direction: column; gap: .5rem; }
        .dark .zz-catalog { background: rgb(39 39 46); border-color: rgba(255,255,255,.1); }
        .zz-catalog-preview { display: flex; align-items: center; gap: .6rem; font-size: .8rem; color: rgb(82 82 91); }
        .dark .zz-catalog-preview { color: rgb(212 212 216); }
        .zz-catalog-preview img { width: 3rem; height: 3rem; object-fit: cover; border-radius: .5rem; }

        .zz-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 3rem 1rem; color: rgb(113 113 122); font-size: .875rem; text-align: center; }
        .zz-manual-form { padding: .75rem 1rem; border-bottom: 1px solid rgba(3,7,18,.08); display: flex; flex-direction: column; gap: .5rem; }
        .dark .zz-manual-form { border-color: rgba(255,255,255,.1); }
    </style>

    {{-- data-zz-no-reload: opt out of the global notificationsSent full-page
         reload — it would close the open conversation after every send.
         wire:poll .visible: near-realtime refresh without background polls
         (background polls on flaky mobile connections trigger Filament's
         "Error while loading page" toast). --}}
    <div
        class="zz-inbox {{ $selectedConversationId ? 'has-sel' : '' }}"
        data-zz-no-reload
        wire:poll.visible.5s
        x-data="{
            scrollBottom(smooth = false) {
                const el = $refs.messages;
                if (el) el.scrollTo({ top: el.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
            },
            nearBottom() {
                const el = $refs.messages;
                return el ? (el.scrollHeight - el.scrollTop - el.clientHeight) < 120 : false;
            },
            init() {
                this.$nextTick(() => this.scrollBottom());
                // New messages arriving via poll: follow the thread like
                // WhatsApp when the user is already reading the bottom.
                new MutationObserver(() => { if (this.nearBottom()) this.scrollBottom(true); })
                    .observe(this.$el, { childList: true, subtree: true });
            },
        }"
        x-on:zz-scroll-bottom.window="$nextTick(() => scrollBottom(true))"
    >
        {{-- Conversation list --}}
        <div class="zz-panel zz-list-panel">
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
                    @php($displayName = $conversation->contact_name ?: $conversation->contact_phone ?: 'Contact '.$conversation->external_contact_id)
                    <button
                        type="button"
                        wire:click="selectConversation({{ $conversation->getKey() }})"
                        wire:key="conv-{{ $conversation->getKey() }}"
                        class="zz-conv {{ $selectedConversationId === $conversation->getKey() ? 'selected' : '' }}"
                    >
                        <span class="zz-avatar">{{ mb_strtoupper(mb_substr(trim($displayName), 0, 1)) }}</span>
                        <span class="zz-conv-main">
                            <span class="zz-conv-top">
                                <span class="zz-conv-name">{{ $displayName }}</span>
                                <span class="zz-conv-time">{{ $conversation->last_message_at?->diffForHumans(short: true) }}</span>
                            </span>
                            <span class="zz-conv-bottom">
                                <span class="zz-conv-preview">
                                    @if ($conversation->latestMessage)
                                        @if ($conversation->latestMessage->direction === 'outgoing')✓@endif
                                        @if ($conversation->latestMessage->mediaImageUrl())📷 @endif
                                        {{ \Illuminate\Support\Str::limit((string) $conversation->latestMessage->body, 60) }}
                                    @else
                                        —
                                    @endif
                                </span>
                                @if ($conversation->unread_count > 0)
                                    <span class="zz-unread">{{ $conversation->unread_count }}</span>
                                @endif
                            </span>
                            <span class="zz-conv-tags">
                                <x-filament::badge color="gray" size="sm">{{ \App\Models\Conversation::PROVIDERS[$conversation->provider] ?? $conversation->provider }}</x-filament::badge>
                                @if ($conversation->customer)
                                    <x-filament::badge color="success" size="sm">Customer</x-filament::badge>
                                @elseif ($conversation->lead)
                                    <x-filament::badge color="info" size="sm">Lead</x-filament::badge>
                                @endif
                            </span>
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
                    <x-filament::icon-button
                        class="zz-back"
                        icon="heroicon-o-arrow-left"
                        wire:click="deselectConversation"
                        label="Back to conversations"
                        color="gray"
                    />
                    <span class="zz-avatar" style="width: 2.25rem; height: 2.25rem; font-size: .875rem;">
                        {{ mb_strtoupper(mb_substr(trim($conversation->contact_name ?: $conversation->contact_phone ?: 'C'), 0, 1)) }}
                    </span>
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

                <div class="zz-messages" x-ref="messages">
                    @forelse ($conversation->messages as $message)
                        @php($day = $message->sent_at->isToday() ? 'আজ' : ($message->sent_at->isYesterday() ? 'গতকাল' : $message->sent_at->format('d M Y')))
                        @if ($loop->first || ! $message->sent_at->isSameDay($conversation->messages[$loop->index - 1]->sent_at))
                            <div class="zz-day" wire:key="day-{{ $message->getKey() }}">{{ $day }}</div>
                        @endif
                        <div class="zz-bubble-row {{ $message->direction === 'outgoing' ? 'out' : '' }}" wire:key="msg-{{ $message->getKey() }}">
                            <div class="zz-bubble">
                                @if (! in_array($message->type, ['text', 'note'], true))
                                    <p class="kind">{{ \App\Models\ConversationMessage::TYPES[$message->type] ?? $message->type }}</p>
                                @endif
                                @if ($imageUrl = $message->mediaImageUrl())
                                    <img class="media" src="{{ $imageUrl }}" alt="Attached image" loading="lazy">
                                @elseif ($mediaUrl = $message->mediaDownloadUrl())
                                    <p class="kind">
                                        <a href="{{ $mediaUrl }}" target="_blank" rel="noopener noreferrer">📎 {{ basename($message->media_path) }}</a>
                                    </p>
                                @endif
                                <p class="body">{!! $message->bodyHtml() !!}</p>
                                <p class="stamp">
                                    @if ($message->generated_by === 'ai')<span>🤖 AI</span>
                                    @elseif ($message->sender)<span>{{ $message->sender->name }}</span>@endif
                                    <span>{{ $message->sent_at->format('H:i') }}</span>
                                    @if ($message->direction === 'outgoing')
                                        <span class="zz-tick {{ $message->delivery_status === 'failed' ? 'failed' : '' }}">
                                            @if ($message->delivery_status === 'failed') ⚠
                                            @elseif (in_array($message->delivery_status, ['delivered', 'read'], true)) ✓✓
                                            @else ✓
                                            @endif
                                        </span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="zz-empty">No messages in this conversation yet.</div>
                    @endforelse
                </div>

                <div class="zz-composer">
                    @if ($showCatalogPanel)
                        <div class="zz-catalog">
                            <div class="zz-row">
                                <div class="grow">
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select wire:model.live="orderFormProductId">
                                            <option value="">ক্যাটালগ থেকে প্রোডাক্ট বেছে নিন…</option>
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
                            </div>
                            @php($selectedProduct = $orderFormProductId ? $this->products->firstWhere('id', (int) $orderFormProductId) : null)
                            @if ($selectedProduct)
                                <div class="zz-catalog-preview">
                                    @if ($selectedProduct->image)
                                        <img src="{{ \App\Support\CompanyMedia::publicUrl($selectedProduct->image, $selectedProduct) }}" alt="{{ $selectedProduct->name }}">
                                    @endif
                                    <span>
                                        <strong>{{ $selectedProduct->name }}</strong><br>
                                        ৳{{ number_format((float) $selectedProduct->sale_price, 2) }} — ছবিসহ প্রোডাক্ট কার্ড ও অর্ডার লিংক যাবে
                                    </span>
                                </div>
                            @endif
                            <x-filament::button wire:click="sendOrderForm" color="success" icon="heroicon-m-shopping-cart" size="sm">
                                অর্ডার ফর্ম পাঠান
                            </x-filament::button>
                        </div>
                    @endif

                    <div class="zz-row" style="align-items: center;">
                        <button
                            type="button"
                            class="zz-round-btn ghost {{ $showCatalogPanel ? 'on' : '' }}"
                            wire:click="$toggle('showCatalogPanel')"
                            title="ক্যাটালগ / অর্ডার ফর্ম পাঠান"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        </button>
                        <div class="zz-pill">
                            <textarea
                                wire:model="replyBody"
                                x-data
                                x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.sendReply(); }"
                                rows="1"
                                x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 112) + 'px'"
                                placeholder="{{ in_array($conversation->provider, ['whatsapp', 'messenger'], true) ? 'মেসেজ লিখুন…' : 'ইন্টারনাল নোট লিখুন…' }}"
                            ></textarea>
                        </div>
                        <button type="button" class="zz-round-btn" wire:click="sendReply" title="Send" wire:loading.attr="disabled" wire:target="sendReply">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.4 20.4l17.4-7.5c.8-.4.8-1.5 0-1.8L3.4 3.6c-.7-.3-1.4.3-1.4.9L2 9.1c0 .5.4.9.9 1l12.4 1.9L2.9 13.9c-.5.1-.9.5-.9 1l0 4.6c0 .7.7 1.2 1.4.9z"/></svg>
                        </button>
                    </div>
                </div>
            @else
                <div class="zz-empty">কথোপকথন দেখতে বাম পাশ থেকে একটি চ্যাট বেছে নিন।</div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
