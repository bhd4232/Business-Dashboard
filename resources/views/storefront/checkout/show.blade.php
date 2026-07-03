@extends('storefront.layout')

@section('content')
    <section class="border-b border-gray-200 dark:border-white/10">
        <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Delivery details</h1>
            <p class="mt-3 max-w-2xl text-base text-gray-600 dark:text-gray-300">
                Submit your storefront order. The ERP team will review and confirm it before stock is deducted.
            </p>
        </div>
    </section>

    <section class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[1fr_360px] lg:px-8">
        <form class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/5" method="POST" action="{{ isset($previewSlug) ? route('storefront.preview.checkout.store', $previewSlug) : route('storefront.checkout.store') }}">
            @csrf

            <div class="grid gap-5 sm:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Full name</span>
                    <input class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-950 outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" name="name" value="{{ old('name') }}" required>
                    @error('name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Phone number</span>
                    <input class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-950 outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" name="phone" value="{{ old('phone') }}" required>
                    @error('phone') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Email address <span class="text-gray-400">(optional)</span></span>
                    <input class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-950 outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" type="email" name="email" value="{{ old('email') }}">
                    @error('email') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Delivery address</span>
                    <textarea class="mt-2 min-h-28 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-950 outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" name="address" required>{{ old('address') }}</textarea>
                    @error('address') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Order note <span class="text-gray-400">(optional)</span></span>
                    <textarea class="mt-2 min-h-24 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-950 outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" name="note">{{ old('note') }}</textarea>
                    @error('note') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <button class="mt-8 w-full rounded-lg bg-gray-950 px-6 py-3 text-sm font-medium text-white transition hover:bg-[var(--storefront-brand)] dark:bg-white dark:text-gray-950" type="submit">
                Place storefront order
            </button>
        </form>

        <aside class="h-fit rounded-xl border border-gray-200 bg-white p-6 lg:sticky lg:top-24 dark:border-white/10 dark:bg-white/5">
            <h2 class="text-lg font-semibold">Order summary</h2>
            <div class="mt-5 space-y-3">
                @foreach ($items as $item)
                    <div class="flex justify-between gap-4 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">{{ $item['product']->name }}{{ ($item['variant'] ?? null) ? ' ('.$item['variant']->label().')' : '' }} &times; {{ $item['quantity'] }}</span>
                        <span class="font-semibold">BDT {{ number_format($item['subtotal'], 2) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-5 border-t border-gray-200 pt-5 dark:border-white/10">
                <div class="flex justify-between text-lg font-semibold">
                    <span>Total</span>
                    <span>BDT {{ number_format($subtotal, 2) }}</span>
                </div>
                <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    Delivery charge and payment collection will be handled after review.
                </p>
            </div>
        </aside>
    </section>
@endsection
