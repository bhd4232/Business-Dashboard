@extends('storefront.layout')

@php
    $currency = $company->currency ?: 'BDT';
    $ordersUrl = isset($previewSlug) ? route('storefront.preview.account.orders', $previewSlug) : route('storefront.account.orders');
    $isPreview = isset($previewSlug);
@endphp

@section('content')
    <section class="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr]">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[var(--storefront-brand)]">Customer account</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $isPreview ? 'Find storefront orders.' : 'Your order history.' }}</h1>
                <p class="mt-4 text-base leading-7 text-gray-600 dark:text-gray-300">
                    {{ $isPreview
                        ? 'Enter the phone number used at checkout to preview order history and live tracking.'
                        : 'Review your '.$company->name.' purchases, track delivery progress, or add available items to your cart again.' }}
                </p>

                @if ($isPreview)
                    <form class="mt-8 rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5" method="GET" action="{{ $ordersUrl }}">
                        <label class="text-xs font-medium text-gray-500" for="phone">Checkout phone number</label>
                        <div class="mt-2 flex flex-col gap-3 sm:flex-row">
                            <input
                                class="min-h-11 flex-1 rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950"
                                id="phone"
                                name="phone"
                                placeholder="Example: 01728174614"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                                value="{{ $phone }}"
                            >
                            <button class="rounded-lg bg-[var(--storefront-brand)] px-6 py-2.5 text-sm font-medium text-white transition hover:opacity-90" type="submit">
                                Show orders
                            </button>
                        </div>
                        <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">
                            Preview access is available only to local or authenticated administrators.
                        </p>
                    </form>
                @else
                    <div class="mt-8 rounded-xl border border-gray-200 bg-white p-5 text-sm leading-6 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                        Your account keeps this history private. Tracking links from here do not expose your phone number in the URL.
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-5 dark:border-white/10">
                    <h2 class="text-lg font-semibold tracking-tight">Your orders</h2>
                    @if ($orders->isNotEmpty())
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                            {{ $orders->count() }} {{ str('order')->plural($orders->count()) }}
                        </span>
                    @endif
                </div>

                @if (! $hasSearched)
                    <div class="grid min-h-80 place-items-center text-center">
                        <div>
                            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-gray-100 text-xl text-gray-500 dark:bg-white/10 dark:text-gray-300">☎</div>
                            <h3 class="mt-5 text-xl font-semibold tracking-tight">Search by phone</h3>
                            <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-gray-500 dark:text-gray-400">
                                Use the same phone number you entered during checkout.
                            </p>
                        </div>
                    </div>
                @elseif ($orders->isEmpty())
                    <div class="grid min-h-80 place-items-center text-center">
                        <div>
                            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-gray-100 text-xl text-gray-500 dark:bg-white/10 dark:text-gray-300">!</div>
                            <h3 class="mt-5 text-xl font-semibold tracking-tight">{{ $isPreview ? 'No storefront orders found' : 'No orders yet' }}</h3>
                            <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-gray-500 dark:text-gray-400">
                                {{ $isPreview
                                    ? 'Check the phone number or contact '.$company->name.' if the order was placed another way.'
                                    : 'Orders placed with this account will appear here after checkout.' }}
                            </p>
                        </div>
                    </div>
                @else
                    <div class="mt-6 space-y-4">
                        @foreach ($orders as $order)
                            @php
                                $trackUrl = $isPreview
                                    ? route('storefront.preview.track.show', ['company' => $previewSlug, 'orderNo' => $order->order_number, 'phone' => $phone])
                                    : route('storefront.track.show', ['orderNo' => $order->order_number]);
                                $reorderUrl = $isPreview
                                    ? route('storefront.preview.account.reorder', [$previewSlug, $order->order_number])
                                    : route('storefront.account.reorder', $order->order_number);
                                $statusLabel = App\Models\Order::STATUSES[$order->status] ?? str($order->status)->headline();
                                $itemCount = $order->items->sum('quantity');
                            @endphp

                            <article class="rounded-xl border border-gray-200 bg-gray-50 p-5 transition hover:border-[var(--storefront-brand)] hover:bg-white hover:shadow-sm dark:border-white/10 dark:bg-gray-950 dark:hover:bg-white/5">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <div class="text-xs font-medium text-gray-400">Order number</div>
                                        <h3 class="mt-1 text-lg font-semibold tracking-tight">{{ $order->order_number }}</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            {{ optional($order->order_date)->format('d M Y') }} &middot; {{ $itemCount }} {{ str('item')->plural($itemCount) }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs font-medium text-gray-400">Total</div>
                                        <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $currency }} {{ number_format((float) $order->total_amount, 2) }}</div>
                                    </div>
                                </div>

                                <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                                    <span class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-gray-600 shadow-sm dark:bg-white/10 dark:text-gray-300">
                                        {{ $statusLabel }}
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ $reorderUrl }}">
                                            @csrf
                                            @if ($isPreview)
                                                <input type="hidden" name="phone" value="{{ $phone }}">
                                            @endif
                                            <button class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-900 transition hover:border-gray-950 dark:border-white/15 dark:text-white dark:hover:border-white" type="submit">
                                                Reorder
                                            </button>
                                        </form>
                                        <a class="inline-flex rounded-lg bg-[var(--storefront-brand)] px-4 py-2 text-sm font-medium text-white transition hover:opacity-90" href="{{ $trackUrl }}">
                                            Track order
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
