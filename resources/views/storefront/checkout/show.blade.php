@extends('storefront.layout')

@section('content')
    @php
        $insideCharge = (float) ($setting->delivery_charge_inside ?? 0);
        $outsideCharge = (float) ($setting->delivery_charge_outside ?? 0);
        $codEnabled = $setting->cod_enabled ?? true;
        $hasBkash = filled($setting->manual_bkash_number);
        $hasNagad = filled($setting->manual_nagad_number);
    @endphp

    <section class="border-b border-gray-200 dark:border-white/10">
        <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Delivery details</h1>
            <p class="mt-3 max-w-2xl text-base text-gray-600 dark:text-gray-300">
                Submit your storefront order. The ERP team will review and confirm it before stock is deducted.
            </p>
        </div>
    </section>

    <section
        class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[1fr_360px] lg:px-8"
        x-data="{
            area: '{{ old('delivery_area', 'inside') }}',
            method: '{{ old('payment_method', 'cod') }}',
            subtotal: {{ $subtotal }},
            insideCharge: {{ $insideCharge }},
            outsideCharge: {{ $outsideCharge }},
            get deliveryCharge() { return this.area === 'inside' ? this.insideCharge : this.outsideCharge; },
            get total() { return this.subtotal + this.deliveryCharge; }
        }"
    >
        <form id="checkout-form" class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/5" method="POST" action="{{ isset($previewSlug) ? route('storefront.preview.checkout.store', $previewSlug) : route('storefront.checkout.store') }}">
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

                <div class="block sm:col-span-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Delivery area</span>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <label class="flex cursor-pointer items-center justify-between rounded-lg border px-4 py-3 text-sm transition" :class="area === 'inside' ? 'border-[var(--storefront-brand)] ring-1 ring-[var(--storefront-brand)]' : 'border-gray-300 dark:border-white/15'">
                            <span>
                                <span class="block font-medium text-gray-900 dark:text-white">Inside Dhaka</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">BDT {{ number_format($insideCharge, 2) }}</span>
                            </span>
                            <input type="radio" name="delivery_area" value="inside" x-model="area" class="h-4 w-4">
                        </label>
                        <label class="flex cursor-pointer items-center justify-between rounded-lg border px-4 py-3 text-sm transition" :class="area === 'outside' ? 'border-[var(--storefront-brand)] ring-1 ring-[var(--storefront-brand)]' : 'border-gray-300 dark:border-white/15'">
                            <span>
                                <span class="block font-medium text-gray-900 dark:text-white">Outside Dhaka</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">BDT {{ number_format($outsideCharge, 2) }}</span>
                            </span>
                            <input type="radio" name="delivery_area" value="outside" x-model="area" class="h-4 w-4">
                        </label>
                    </div>
                    @error('delivery_area') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="block sm:col-span-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Payment method</span>
                    <div class="mt-2 space-y-3">
                        @if ($codEnabled)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm transition" :class="method === 'cod' ? 'border-[var(--storefront-brand)] ring-1 ring-[var(--storefront-brand)]' : 'border-gray-300 dark:border-white/15'">
                                <input type="radio" name="payment_method" value="cod" x-model="method" class="h-4 w-4">
                                <span class="font-medium text-gray-900 dark:text-white">Cash on Delivery</span>
                            </label>
                        @endif

                        @if ($hasBkash)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm transition" :class="method === 'manual_bkash' ? 'border-[var(--storefront-brand)] ring-1 ring-[var(--storefront-brand)]' : 'border-gray-300 dark:border-white/15'">
                                <input type="radio" name="payment_method" value="manual_bkash" x-model="method" class="h-4 w-4">
                                <span class="font-medium text-gray-900 dark:text-white">bKash (Send Money)</span>
                            </label>
                            <div x-show="method === 'manual_bkash'" x-cloak class="ml-1 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm dark:border-white/10 dark:bg-white/5">
                                <p class="text-gray-700 dark:text-gray-200">Send Money to <span class="font-semibold">{{ $setting->manual_bkash_number }}</span></p>
                                @if ($setting->manual_bkash_instructions)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $setting->manual_bkash_instructions }}</p>
                                @endif
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Your bKash number</span>
                                        <input class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none dark:border-white/15 dark:bg-white/10 dark:text-white" name="sender_number" value="{{ old('sender_number') }}" x-bind:required="method === 'manual_bkash'">
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Transaction ID</span>
                                        <input class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none dark:border-white/15 dark:bg-white/10 dark:text-white" name="trx_id" value="{{ old('trx_id') }}" x-bind:required="method === 'manual_bkash'">
                                    </label>
                                </div>
                            </div>
                        @endif

                        @if ($hasNagad)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm transition" :class="method === 'manual_nagad' ? 'border-[var(--storefront-brand)] ring-1 ring-[var(--storefront-brand)]' : 'border-gray-300 dark:border-white/15'">
                                <input type="radio" name="payment_method" value="manual_nagad" x-model="method" class="h-4 w-4">
                                <span class="font-medium text-gray-900 dark:text-white">Nagad (Send Money)</span>
                            </label>
                            <div x-show="method === 'manual_nagad'" x-cloak class="ml-1 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm dark:border-white/10 dark:bg-white/5">
                                <p class="text-gray-700 dark:text-gray-200">Send Money to <span class="font-semibold">{{ $setting->manual_nagad_number }}</span></p>
                                @if ($setting->manual_nagad_instructions)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $setting->manual_nagad_instructions }}</p>
                                @endif
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Your Nagad number</span>
                                        <input class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none dark:border-white/15 dark:bg-white/10 dark:text-white" name="sender_number" value="{{ old('sender_number') }}" x-bind:required="method === 'manual_nagad'">
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Transaction ID</span>
                                        <input class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none dark:border-white/15 dark:bg-white/10 dark:text-white" name="trx_id" value="{{ old('trx_id') }}" x-bind:required="method === 'manual_nagad'">
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>
                    @error('payment_method') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                    @error('sender_number') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                    @error('trx_id') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Order note <span class="text-gray-400">(optional)</span></span>
                    <textarea class="mt-2 min-h-24 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-950 outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" name="note">{{ old('note') }}</textarea>
                    @error('note') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <button class="mt-8 w-full rounded-lg bg-gray-950 px-6 py-3 text-sm font-medium text-white transition hover:bg-[var(--storefront-brand)] dark:bg-white dark:text-gray-950" type="submit" data-checkout-submit>
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
            <div class="mt-5 space-y-2 border-t border-gray-200 pt-5 text-sm dark:border-white/10">
                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                    <span>Subtotal</span>
                    <span>BDT {{ number_format($subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                    <span>Delivery charge</span>
                    <span x-text="'BDT ' + deliveryCharge.toFixed(2)"></span>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-2 text-lg font-semibold text-gray-950 dark:border-white/10 dark:text-white">
                    <span>Total</span>
                    <span x-text="'BDT ' + total.toFixed(2)"></span>
                </div>
            </div>
            @if (($advanceDue ?? 0) > 0)
                <div class="mt-4 rounded-lg border border-[var(--storefront-brand)]/30 bg-[var(--storefront-brand)]/5 px-4 py-3 text-sm leading-6 text-gray-700 dark:text-gray-200">
                    <div class="flex justify-between font-semibold">
                        <span>Advance payable online now</span>
                        <span>BDT {{ number_format($advanceDue, 2) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Your cart includes pre-order items. You will be redirected to a secure payment page after placing the order.
                    </p>
                    @unless ($onlinePaymentAvailable ?? false)
                        <p class="mt-2 text-xs font-medium text-red-600 dark:text-red-400">
                            Online payment is currently unavailable. Please contact the store to complete this pre-order.
                        </p>
                    @endunless
                </div>
            @endif
        </aside>
    </section>
@endsection
