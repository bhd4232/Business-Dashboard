@extends('storefront.layout')

@php
    $currency = $company->currency ?: 'BDT';
    $trackIndexUrl = isset($previewSlug) ? route('storefront.preview.track.index', $previewSlug) : route('storefront.track.index');
    $booking = $order?->latestCourierBooking;
    $orderStatusLabel = $order ? (App\Models\Order::STATUSES[$order->status] ?? ucfirst((string) $order->status)) : null;
    $deliveryStatus = $order?->delivery_status ?? App\Models\CourierBooking::STATUS_NOT_BOOKED;
    $deliveryStatusLabel = App\Models\Order::DELIVERY_STATUSES[$deliveryStatus] ?? str($deliveryStatus)->headline();
    $hasDeliveryUpdate = $order && ($deliveryStatus !== App\Models\CourierBooking::STATUS_NOT_BOOKED || $booking);
    $orderSteps = [
        ['key' => 'submitted', 'label' => 'Order submitted', 'done' => (bool) $order],
        ['key' => 'confirmed', 'label' => 'Admin confirmed', 'done' => $order && in_array($order->status, ['confirmed', 'completed'], true)],
    ];
@endphp

@section('content')
    <section class="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-[0.85fr_1.15fr]">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-[var(--storefront-brand)]">Order tracking</p>
                <h1 class="mt-3 text-4xl font-black tracking-[-0.05em] sm:text-6xl">Track your storefront order.</h1>
                <p class="mt-5 text-lg leading-8 text-stone-600 dark:text-stone-300">
                    Enter your ERP order number to see the latest order, delivery, and courier information from {{ $company->name }}.
                </p>

                <form class="mt-8 rounded-[2rem] border border-stone-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5" method="GET" action="{{ $trackIndexUrl }}">
                    <label class="text-xs font-black uppercase tracking-widest text-stone-500" for="order_number">Order number</label>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                        <input
                            class="min-h-12 flex-1 rounded-full border border-stone-200 bg-stone-50 px-5 text-sm font-bold outline-none transition focus:border-[var(--storefront-brand)] focus:bg-white dark:border-white/10 dark:bg-stone-950 dark:focus:bg-stone-900"
                            id="order_number"
                            name="order_number"
                            placeholder="Example: {{ $company->invoice_prefix }}-{{ now()->format('Ymd') }}-0001"
                            type="text"
                            value="{{ $order?->order_number }}"
                        >
                        <button class="rounded-full bg-[var(--storefront-brand)] px-6 py-3 text-sm font-black text-white shadow-lg shadow-stone-900/10 transition hover:-translate-y-0.5" type="submit">
                            Track order
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-[2rem] border border-stone-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5">
                @if (! $order)
                    <div class="grid min-h-80 place-items-center text-center">
                        <div>
                            <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-stone-100 text-2xl font-black text-stone-500 dark:bg-white/10 dark:text-stone-300">#</div>
                            <h2 class="mt-5 text-2xl font-black tracking-tight">Enter an order number</h2>
                            <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-stone-500 dark:text-stone-400">
                                After checkout, use the order number from the success page or invoice to track status here.
                            </p>
                        </div>
                    </div>
                @else
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-stone-200 pb-5 dark:border-white/10">
                        <div>
                            <div class="text-xs font-black uppercase tracking-widest text-stone-500">Order number</div>
                            <h2 class="mt-1 text-2xl font-black tracking-tight">{{ $order->order_number }}</h2>
                            <p class="mt-1 text-sm font-bold text-stone-500 dark:text-stone-400">{{ optional($order->order_date)->format('d M Y') }}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-black uppercase tracking-widest text-stone-500">Total due</div>
                            <div class="mt-1 text-2xl font-black text-[var(--storefront-brand)]">{{ $currency }} {{ number_format((float) $order->due_amount, 2) }}</div>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-3xl bg-stone-50 p-4 dark:bg-stone-950">
                            <div class="text-xs font-black uppercase tracking-widest text-stone-500">Order status</div>
                            <div class="mt-2 text-lg font-black">{{ $orderStatusLabel }}</div>
                        </div>
                        @if ($hasDeliveryUpdate)
                            <div class="rounded-3xl bg-stone-50 p-4 dark:bg-stone-950">
                                <div class="text-xs font-black uppercase tracking-widest text-stone-500">Delivery</div>
                                <div class="mt-2 text-lg font-black">{{ $deliveryStatusLabel }}</div>
                            </div>
                        @endif
                        @if ($booking)
                            <div class="rounded-3xl bg-stone-50 p-4 dark:bg-stone-950">
                                <div class="text-xs font-black uppercase tracking-widest text-stone-500">Courier</div>
                                <div class="mt-2 text-lg font-black">{{ $booking->provider?->name ?? 'Booked' }}</div>
                            </div>
                        @endif
                    </div>

                    <div class="mt-8">
                        <h3 class="text-sm font-black uppercase tracking-[0.2em] text-stone-500">Order progress</h3>
                        <div class="mt-4 space-y-3">
                            @foreach ($orderSteps as $step)
                                <div class="flex items-center gap-3 rounded-3xl border px-4 py-3 {{ $step['done'] ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200' : 'border-stone-200 bg-stone-50 text-stone-500 dark:border-white/10 dark:bg-stone-950 dark:text-stone-400' }}">
                                    <span class="grid h-8 w-8 place-items-center rounded-full {{ $step['done'] ? 'bg-emerald-600 text-white' : 'bg-stone-200 text-stone-500 dark:bg-white/10' }}">
                                        {{ $step['done'] ? '✓' : '•' }}
                                    </span>
                                    <span class="text-sm font-black">{{ $step['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if ($hasDeliveryUpdate)
                        <div class="mt-8">
                            <h3 class="text-sm font-black uppercase tracking-[0.2em] text-stone-500">Delivery update</h3>
                            <div class="mt-4 flex items-center gap-3 rounded-3xl border border-[var(--storefront-brand)] bg-[var(--storefront-brand)]/10 px-4 py-3 text-stone-950 dark:text-white">
                                <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--storefront-brand)] text-white">●</span>
                                <span class="text-sm font-black">{{ $deliveryStatusLabel }}</span>
                            </div>
                        </div>
                    @endif

                    @if ($booking)
                        <div class="mt-8 rounded-[2rem] border border-stone-200 p-5 dark:border-white/10">
                            <h3 class="text-sm font-black uppercase tracking-[0.2em] text-stone-500">Courier details</h3>
                            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <span class="block font-black">Tracking ID</span>
                                    <span class="text-stone-600 dark:text-stone-300">{{ $booking->tracking_id ?: 'Pending' }}</span>
                                </div>
                                <div>
                                    <span class="block font-black">Booking status</span>
                                    <span class="text-stone-600 dark:text-stone-300">{{ App\Models\CourierBooking::STATUSES[$booking->status] ?? str($booking->status)->headline() }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($trackingUpdates->isNotEmpty())
                        <div class="mt-10 rounded-[2rem] border border-stone-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5 sm:p-6">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-200 pb-5 dark:border-white/10">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-[0.22em] text-[var(--storefront-brand)]">Live order flow</p>
                                    <h3 class="mt-1 text-2xl font-black tracking-tight">Tracking Updates</h3>
                                </div>
                                <span class="rounded-full bg-stone-100 px-4 py-2 text-xs font-black uppercase tracking-widest text-stone-600 dark:bg-white/10 dark:text-stone-300">
                                    {{ $trackingUpdates->count() }} {{ str('update')->plural($trackingUpdates->count()) }}
                                </span>
                            </div>

                            <div class="mt-6">
                                <div class="space-y-3">
                                    @foreach ($trackingUpdates as $update)
                                        @php
                                            $isLatest = $loop->first;
                                            $isDelivery = $update['type'] === 'delivery';
                                        @endphp
                                        <div class="relative grid gap-3 sm:grid-cols-[2.25rem_1fr]">
                                            <div class="relative hidden justify-center sm:flex">
                                                @unless ($loop->last)
                                                    <span class="absolute left-1/2 top-10 h-[calc(100%+0.75rem)] w-px -translate-x-1/2 bg-stone-200 dark:bg-white/10"></span>
                                                @endunless
                                                <span class="relative z-10 mt-4 grid h-9 w-9 place-items-center rounded-[100px] border-4 border-white text-sm shadow-sm dark:border-stone-950 {{ $isDelivery ? 'bg-emerald-500 text-white' : 'bg-sky-500 text-white' }}">
                                                    {{ $isDelivery ? '✓' : '↗' }}
                                                </span>
                                            </div>

                                            <article class="rounded-[1.35rem] border p-4 transition {{ $isLatest ? 'border-[var(--storefront-brand)] bg-[var(--storefront-brand)]/5 shadow-lg shadow-stone-900/5' : 'border-stone-200 bg-stone-50 dark:border-white/10 dark:bg-stone-950' }}">
                                                <div class="flex gap-3 sm:gap-4">
                                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-[100px] text-sm sm:hidden {{ $isDelivery ? 'bg-emerald-500 text-white' : 'bg-sky-500 text-white' }}">
                                                        {{ $isDelivery ? '✓' : '↗' }}
                                                    </span>

                                                    <div class="min-w-0 flex-1">
                                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                                        <div>
                                                            <h4 class="font-black leading-6">{{ $update['title'] }}</h4>
                                                            <p class="mt-1 text-sm leading-6 text-stone-600 dark:text-stone-300">{{ $update['message'] }}</p>
                                                        </div>

                                                        <div class="flex shrink-0 items-center gap-2">
                                                            @if ($isLatest)
                                                                <span class="rounded-[100px] bg-[var(--storefront-brand)] px-[10px] py-0.5 text-[9px] font-black uppercase tracking-[0.14em] text-white">Latest</span>
                                                            @endif
                                                            <time class="rounded-2xl bg-white px-3 py-2 text-right text-xs font-black text-stone-500 shadow-sm dark:bg-white/10 dark:text-stone-300" datetime="{{ $update['time']->toIso8601String() }}">
                                                                <span class="block">{{ $update['time']->format('M d, Y') }}</span>
                                                                <span class="block text-[var(--storefront-brand)]">{{ $update['time']->format('h:i A') }}</span>
                                                            </time>
                                                        </div>
                                                    </div>
                                                </div>
                                                </div>
                                            </article>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="mt-8">
                        <h3 class="text-sm font-black uppercase tracking-[0.2em] text-stone-500">Order items</h3>
                        <div class="mt-4 space-y-3">
                            @foreach ($order->items as $item)
                                <div class="flex justify-between gap-4 rounded-3xl bg-stone-50 p-4 text-sm dark:bg-stone-950">
                                    <div>
                                        <div class="font-black">{{ $item->product?->name ?? 'Product' }}</div>
                                        <div class="mt-1 text-stone-500">Qty {{ $item->quantity }}</div>
                                    </div>
                                    <div class="font-black">{{ $currency }} {{ number_format((float) $item->subtotal, 2) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
