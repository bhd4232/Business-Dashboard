@extends('storefront.layout')

@section('content')
    <section class="border-b border-stone-200 bg-white dark:border-white/10 dark:bg-stone-950">
        <div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <p class="text-sm font-black uppercase tracking-[0.22em] text-[var(--storefront-brand)]">Checkout</p>
            <h1 class="mt-3 text-4xl font-black tracking-[-0.05em] sm:text-6xl">Delivery details</h1>
            <p class="mt-4 max-w-2xl text-lg leading-8 text-stone-600 dark:text-stone-300">
                Submit your storefront order. The ERP team will review and confirm it before stock is deducted.
            </p>
        </div>
    </section>

    <section class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_380px] lg:px-8">
        <form class="rounded-[2rem] border border-stone-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5" method="POST" action="{{ isset($previewSlug) ? route('storefront.preview.checkout.store', $previewSlug) : route('storefront.checkout.store') }}">
            @csrf

            <div class="grid gap-5 sm:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-black text-stone-700 dark:text-stone-200">Full name</span>
                    <input class="mt-2 w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm font-bold text-stone-950 outline-none transition focus:border-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" name="name" value="{{ old('name') }}" required>
                    @error('name') <span class="mt-1 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="text-sm font-black text-stone-700 dark:text-stone-200">Phone number</span>
                    <input class="mt-2 w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm font-bold text-stone-950 outline-none transition focus:border-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" name="phone" value="{{ old('phone') }}" required>
                    @error('phone') <span class="mt-1 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-black text-stone-700 dark:text-stone-200">Email address <span class="font-semibold text-stone-400">(optional)</span></span>
                    <input class="mt-2 w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm font-bold text-stone-950 outline-none transition focus:border-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" type="email" name="email" value="{{ old('email') }}">
                    @error('email') <span class="mt-1 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-black text-stone-700 dark:text-stone-200">Delivery address</span>
                    <textarea class="mt-2 min-h-28 w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm font-bold text-stone-950 outline-none transition focus:border-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" name="address" required>{{ old('address') }}</textarea>
                    @error('address') <span class="mt-1 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-black text-stone-700 dark:text-stone-200">Order note <span class="font-semibold text-stone-400">(optional)</span></span>
                    <textarea class="mt-2 min-h-24 w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm font-bold text-stone-950 outline-none transition focus:border-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" name="note">{{ old('note') }}</textarea>
                    @error('note') <span class="mt-1 block text-sm font-bold text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <button class="mt-8 w-full rounded-full bg-[var(--storefront-brand)] px-6 py-4 text-sm font-black text-white shadow-xl shadow-stone-900/10 transition hover:-translate-y-0.5" type="submit">
                Place storefront order
            </button>
        </form>

        <aside class="h-fit rounded-[2rem] border border-stone-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-white/5">
            <h2 class="text-2xl font-black">Order summary</h2>
            <div class="mt-6 space-y-4">
                @foreach ($items as $item)
                    <div class="flex justify-between gap-4 text-sm">
                        <span class="font-bold text-stone-600 dark:text-stone-300">{{ $item['product']->name }} × {{ $item['quantity'] }}</span>
                        <span class="font-black">BDT {{ number_format($item['subtotal'], 2) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-6 border-t border-stone-200 pt-6 dark:border-white/10">
                <div class="flex justify-between text-xl font-black">
                    <span>Total</span>
                    <span>BDT {{ number_format($subtotal, 2) }}</span>
                </div>
                <p class="mt-3 text-sm font-semibold leading-6 text-stone-500 dark:text-stone-400">
                    Delivery charge and payment collection will be handled after review.
                </p>
            </div>
        </aside>
    </section>
@endsection
