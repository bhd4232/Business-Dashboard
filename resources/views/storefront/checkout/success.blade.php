@extends('storefront.layout')

@section('content')
    <section class="mx-auto w-full max-w-4xl px-4 py-16 text-center sm:px-6 lg:px-8">
        <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-2xl font-semibold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">
            ✓
        </div>
        <p class="mt-6 text-xs font-semibold uppercase tracking-wider text-[var(--storefront-brand)]">Order submitted</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Thank you, {{ $order->customer?->name }}.</h1>
        <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-gray-600 dark:text-gray-300">
            Your storefront order <span class="font-semibold text-gray-950 dark:text-white">{{ $order->order_number }}</span> has been received. Our team will review and confirm it shortly.
        </p>

        <div class="mt-8 rounded-xl border border-gray-200 bg-white p-6 text-left dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 pb-5 dark:border-white/10">
                <div>
                    <div class="text-xs font-medium text-gray-400">Status</div>
                    <div class="mt-1 text-base font-semibold">{{ ucfirst($order->status) }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs font-medium text-gray-400">Total</div>
                    <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">BDT {{ number_format((float) $order->total_amount, 2) }}</div>
                </div>
            </div>
            <div class="mt-5 space-y-3">
                @foreach ($order->items as $item)
                    <div class="flex justify-between gap-4 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">{{ $item->product?->name }}{{ $item->variant_label ? ' ('.$item->variant_label.')' : '' }} &times; {{ $item->quantity }}</span>
                        <span class="font-semibold">BDT {{ number_format((float) $item->subtotal, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @php $advancePayment = $order->storefrontPayments()->latest()->first(); @endphp
        @if ($advancePayment)
            <div class="mt-4 rounded-xl border px-5 py-4 text-left text-sm {{ $advancePayment->status === 'completed' ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200' : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200' }}">
                <div class="flex justify-between font-semibold">
                    <span>Pre-order advance payment</span>
                    <span>BDT {{ number_format((float) $advancePayment->amount, 2) }} &middot; {{ \App\Models\StorefrontPayment::STATUSES[$advancePayment->status] ?? ucfirst($advancePayment->status) }}</span>
                </div>
                @if ($advancePayment->status !== 'completed')
                    <p class="mt-1 text-xs">If you have completed the payment, the status updates automatically within a few minutes. Otherwise the store will contact you to collect the advance.</p>
                @endif
            </div>
        @endif

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a class="inline-flex rounded-lg bg-gray-950 px-6 py-3 text-sm font-medium text-white dark:bg-white dark:text-gray-950" href="{{ isset($previewSlug) ? route('storefront.preview.track.show', [$previewSlug, $order->order_number]) : route('storefront.track.show', $order->order_number) }}">
                Track this order
            </a>
            <a class="inline-flex rounded-lg border border-gray-300 px-6 py-3 text-sm font-medium text-gray-900 dark:border-white/10 dark:text-white" href="{{ isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index') }}">
                Continue shopping
            </a>
        </div>
        <p class="mt-5 text-sm text-gray-500 dark:text-gray-400">
            Want to see all your storefront orders? Visit
            <a class="font-medium text-[var(--storefront-brand)]" href="{{ isset($previewSlug) ? route('storefront.preview.account.orders', ['company' => $previewSlug, 'phone' => $order->customer?->phone]) : route('storefront.account.orders', ['phone' => $order->customer?->phone]) }}">
                order history
            </a>.
        </p>
    </section>
@endsection
