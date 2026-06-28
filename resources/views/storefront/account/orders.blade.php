@extends('storefront.layout')

@php
    $currency = $company->currency ?: 'BDT';
    $ordersUrl = isset($previewSlug) ? route('storefront.preview.account.orders', $previewSlug) : route('storefront.account.orders');
@endphp

@section('content')
    <section class="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr]">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-[var(--storefront-brand)]">Customer account</p>
                <h1 class="mt-3 text-4xl font-black tracking-[-0.05em] sm:text-6xl">Find your storefront orders.</h1>
                <p class="mt-5 text-lg leading-8 text-stone-600 dark:text-stone-300">
                    Enter the phone number used at checkout to view your {{ $company->name }} order history and open live tracking.
                </p>

                <form class="mt-8 rounded-[2rem] border border-stone-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5" method="GET" action="{{ $ordersUrl }}">
                    <label class="text-xs font-black uppercase tracking-widest text-stone-500" for="phone">Checkout phone number</label>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                        <input
                            class="min-h-12 flex-1 rounded-full border border-stone-200 bg-stone-50 px-5 text-sm font-bold outline-none transition focus:border-[var(--storefront-brand)] focus:bg-white dark:border-white/10 dark:bg-stone-950 dark:focus:bg-stone-900"
                            id="phone"
                            name="phone"
                            placeholder="Example: 01728174614"
                            type="text"
                            value="{{ $phone }}"
                        >
                        <button class="rounded-full bg-[var(--storefront-brand)] px-6 py-3 text-sm font-black text-white shadow-lg shadow-stone-900/10 transition hover:-translate-y-0.5" type="submit">
                            Show orders
                        </button>
                    </div>
                    <p class="mt-3 text-xs font-bold leading-5 text-stone-500 dark:text-stone-400">
                        Only storefront orders from this company are shown here. Admin-created orders stay private to the ERP dashboard.
                    </p>
                </form>
            </div>

            <div class="rounded-[2rem] border border-stone-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-200 pb-5 dark:border-white/10">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-[var(--storefront-brand)]">Order history</p>
                        <h2 class="mt-1 text-2xl font-black tracking-tight">Your orders</h2>
                    </div>
                    @if ($orders->isNotEmpty())
                        <span class="rounded-[100px] bg-stone-100 px-4 py-2 text-xs font-black uppercase tracking-widest text-stone-600 dark:bg-white/10 dark:text-stone-300">
                            {{ $orders->count() }} {{ str('order')->plural($orders->count()) }}
                        </span>
                    @endif
                </div>

                @if (! $hasSearched)
                    <div class="grid min-h-80 place-items-center text-center">
                        <div>
                            <div class="mx-auto grid h-16 w-16 place-items-center rounded-[100px] bg-stone-100 text-2xl font-black text-stone-500 dark:bg-white/10 dark:text-stone-300">☎</div>
                            <h3 class="mt-5 text-2xl font-black tracking-tight">Search by phone</h3>
                            <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-stone-500 dark:text-stone-400">
                                Use the same phone number you entered during checkout.
                            </p>
                        </div>
                    </div>
                @elseif ($orders->isEmpty())
                    <div class="grid min-h-80 place-items-center text-center">
                        <div>
                            <div class="mx-auto grid h-16 w-16 place-items-center rounded-[100px] bg-stone-100 text-2xl font-black text-stone-500 dark:bg-white/10 dark:text-stone-300">!</div>
                            <h3 class="mt-5 text-2xl font-black tracking-tight">No storefront orders found</h3>
                            <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-stone-500 dark:text-stone-400">
                                Check the phone number spelling or contact {{ $company->name }} if you placed the order another way.
                            </p>
                        </div>
                    </div>
                @else
                    <div class="mt-6 space-y-4">
                        @foreach ($orders as $order)
                            @php
                                $trackUrl = isset($previewSlug)
                                    ? route('storefront.preview.track.show', [$previewSlug, $order->order_number])
                                    : route('storefront.track.show', $order->order_number);
                                $statusLabel = App\Models\Order::STATUSES[$order->status] ?? str($order->status)->headline();
                                $itemCount = $order->items->sum('quantity');
                            @endphp

                            <article class="rounded-[1.75rem] border border-stone-200 bg-stone-50 p-5 transition hover:-translate-y-0.5 hover:border-[var(--storefront-brand)] hover:bg-white hover:shadow-xl hover:shadow-stone-900/5 dark:border-white/10 dark:bg-stone-950 dark:hover:bg-white/5">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <div class="text-xs font-black uppercase tracking-widest text-stone-500">Order number</div>
                                        <h3 class="mt-1 text-xl font-black tracking-tight">{{ $order->order_number }}</h3>
                                        <p class="mt-1 text-sm font-bold text-stone-500 dark:text-stone-400">
                                            {{ optional($order->order_date)->format('d M Y') }} · {{ $itemCount }} {{ str('item')->plural($itemCount) }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs font-black uppercase tracking-widest text-stone-500">Total</div>
                                        <div class="mt-1 text-xl font-black text-[var(--storefront-brand)]">{{ $currency }} {{ number_format((float) $order->total_amount, 2) }}</div>
                                    </div>
                                </div>

                                <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                                    <span class="rounded-[100px] bg-white px-4 py-2 text-xs font-black uppercase tracking-widest text-stone-600 shadow-sm dark:bg-white/10 dark:text-stone-300">
                                        {{ $statusLabel }}
                                    </span>
                                    <a class="inline-flex rounded-full bg-[var(--storefront-brand)] px-5 py-3 text-sm font-black text-white shadow-lg shadow-stone-900/10 transition hover:-translate-y-0.5" href="{{ $trackUrl }}">
                                        Track order
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
