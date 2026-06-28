@extends('storefront.layout')

@section('content')
    <section class="mx-auto w-full max-w-4xl px-4 py-16 text-center sm:px-6 lg:px-8">
        <div class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-emerald-100 text-3xl font-black text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">
            ✓
        </div>
        <p class="mt-8 text-sm font-black uppercase tracking-[0.22em] text-[var(--storefront-brand)]">Order submitted</p>
        <h1 class="mt-3 text-4xl font-black tracking-[-0.05em] sm:text-6xl">Thank you, {{ $order->customer?->name }}.</h1>
        <p class="mx-auto mt-5 max-w-2xl text-lg leading-8 text-stone-600 dark:text-stone-300">
            Your storefront order <span class="font-black text-stone-950 dark:text-white">{{ $order->order_number }}</span> has been received. Our team will review and confirm it shortly.
        </p>

        <div class="mt-10 rounded-[2rem] border border-stone-200 bg-white p-6 text-left shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 pb-5 dark:border-white/10">
                <div>
                    <div class="text-xs font-black uppercase tracking-widest text-stone-500">Status</div>
                    <div class="mt-1 text-lg font-black">{{ ucfirst($order->status) }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs font-black uppercase tracking-widest text-stone-500">Total</div>
                    <div class="mt-1 text-2xl font-black text-[var(--storefront-brand)]">BDT {{ number_format((float) $order->total_amount, 2) }}</div>
                </div>
            </div>
            <div class="mt-5 space-y-3">
                @foreach ($order->items as $item)
                    <div class="flex justify-between gap-4 text-sm">
                        <span class="font-bold text-stone-600 dark:text-stone-300">{{ $item->product?->name }} × {{ $item->quantity }}</span>
                        <span class="font-black">BDT {{ number_format((float) $item->subtotal, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-8">
            <a class="inline-flex rounded-full bg-[var(--storefront-brand)] px-6 py-3 text-sm font-black text-white" href="{{ isset($previewSlug) ? route('storefront.preview.track.show', [$previewSlug, $order->order_number]) : route('storefront.track.show', $order->order_number) }}">
                Track this order
            </a>
            <a class="ml-3 inline-flex rounded-full border border-stone-200 bg-white px-6 py-3 text-sm font-black text-stone-950 dark:border-white/10 dark:bg-white/10 dark:text-white" href="{{ isset($previewSlug) ? route('storefront.preview.products.index', $previewSlug) : route('storefront.products.index') }}">
                Continue shopping
            </a>
        </div>
        <p class="mt-5 text-sm font-bold text-stone-500 dark:text-stone-400">
            Want to see all your storefront orders? Visit
            <a class="font-black text-[var(--storefront-brand)]" href="{{ isset($previewSlug) ? route('storefront.preview.account.orders', ['company' => $previewSlug, 'phone' => $order->customer?->phone]) : route('storefront.account.orders', ['phone' => $order->customer?->phone]) }}">
                order history
            </a>.
        </p>
    </section>
@endsection
