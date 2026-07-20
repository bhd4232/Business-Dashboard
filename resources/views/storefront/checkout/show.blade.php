@extends('storefront.layout')

@section('content')
    @php
        $insideCharge = (float) ($setting->delivery_charge_inside ?? 0);
        $outsideCharge = (float) ($setting->delivery_charge_outside ?? 0);
        $codEnabled = $setting->cod_enabled ?? true;
        $hasBkash = filled($setting->manual_bkash_number);
        $hasNagad = filled($setting->manual_nagad_number);
        $availablePaymentMethods = array_values(array_filter([
            $codEnabled ? 'cod' : null,
            $hasBkash ? 'manual_bkash' : null,
            $hasNagad ? 'manual_nagad' : null,
        ]));
        $oldDeliveryArea = old('delivery_area', 'inside');
        $defaultDeliveryArea = in_array($oldDeliveryArea, ['inside', 'outside'], true) ? $oldDeliveryArea : 'inside';
        $oldPaymentMethod = old('payment_method');
        $defaultPaymentMethod = in_array($oldPaymentMethod, $availablePaymentMethods, true)
            ? $oldPaymentMethod
            : ($availablePaymentMethods[0] ?? '');
        $hasPaymentPath = $availablePaymentMethods !== [];
        $preorderPaymentBlocked = ($advanceDue ?? 0) > 0 && ! ($onlinePaymentAvailable ?? false);
        $checkoutBlocked = ! $hasPaymentPath || $preorderPaymentBlocked;
        $checkoutBlockDescription = implode(' ', array_filter([
            ! $hasPaymentPath ? 'checkout-payment-unavailable' : null,
            $preorderPaymentBlocked ? 'checkout-preorder-payment-unavailable' : null,
        ]));
        // Computed here (not read from layout.blade.php) since child @section
        // content executes before the parent layout's own @php block runs.
        $loggedInCustomer = auth('customer')->user();
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
            area: {{ Illuminate\Support\Js::from($defaultDeliveryArea) }},
            method: {{ Illuminate\Support\Js::from($defaultPaymentMethod) }},
            subtotal: {{ $subtotal }},
            insideCharge: {{ $insideCharge }},
            outsideCharge: {{ $outsideCharge }},
            get deliveryCharge() { return this.area === 'inside' ? this.insideCharge : this.outsideCharge; },
            get total() { return this.subtotal + this.deliveryCharge; }
        }"
    >
        <form id="checkout-form" class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/5" method="POST" action="{{ isset($previewSlug) ? route('storefront.preview.checkout.store', $previewSlug) : route('storefront.checkout.store') }}" @if ($errors->any()) aria-describedby="checkout-errors" @endif>
            @csrf

            @if ($errors->any())
                <div id="checkout-errors" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200" role="alert" tabindex="-1" data-checkout-errors>
                    <p class="font-semibold">Please review the highlighted checkout details.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Full name</span>
                    <input id="checkout-name" class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-950 outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" name="name" autocomplete="name" value="{{ old('name', $loggedInCustomer->name ?? '') }}" required @error('name') aria-invalid="true" aria-describedby="checkout-name-error" @enderror>
                    @error('name') <span id="checkout-name-error" class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Phone number</span>
                    <input id="checkout-phone" class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-950 outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" type="tel" inputmode="tel" name="phone" autocomplete="tel" value="{{ old('phone', $loggedInCustomer->phone ?? '') }}" required @error('phone') aria-invalid="true" aria-describedby="checkout-phone-error" @enderror>
                    @error('phone') <span id="checkout-phone-error" class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Email address <span class="text-gray-400">(optional)</span></span>
                    <input id="checkout-email" class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-950 outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" type="email" name="email" autocomplete="email" spellcheck="false" value="{{ old('email', $loggedInCustomer->email ?? '') }}" @error('email') aria-invalid="true" aria-describedby="checkout-email-error" @enderror>
                    @error('email') <span id="checkout-email-error" class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Delivery address</span>
                    <textarea id="checkout-address" class="mt-2 min-h-28 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-950 outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" name="address" autocomplete="street-address" required @error('address') aria-invalid="true" aria-describedby="checkout-address-error" @enderror>{{ old('address', $loggedInCustomer->address ?? '') }}</textarea>
                    @error('address') <span id="checkout-address-error" class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <fieldset id="checkout-delivery-area" class="block sm:col-span-2" @error('delivery_area') aria-invalid="true" aria-describedby="checkout-delivery-area-error" @enderror>
                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-200">Delivery area</legend>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <label class="flex cursor-pointer items-center justify-between rounded-lg border px-4 py-3 text-sm transition" :class="area === 'inside' ? 'border-[var(--storefront-brand)] ring-1 ring-[var(--storefront-brand)]' : 'border-gray-300 dark:border-white/15'">
                            <span>
                                <span class="block font-medium text-gray-900 dark:text-white">Inside Dhaka</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">BDT {{ number_format($insideCharge, 2) }}</span>
                            </span>
                            <input id="delivery-area-inside" type="radio" name="delivery_area" value="inside" x-model="area" class="h-4 w-4" required @checked($defaultDeliveryArea === 'inside')>
                        </label>
                        <label class="flex cursor-pointer items-center justify-between rounded-lg border px-4 py-3 text-sm transition" :class="area === 'outside' ? 'border-[var(--storefront-brand)] ring-1 ring-[var(--storefront-brand)]' : 'border-gray-300 dark:border-white/15'">
                            <span>
                                <span class="block font-medium text-gray-900 dark:text-white">Outside Dhaka</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">BDT {{ number_format($outsideCharge, 2) }}</span>
                            </span>
                            <input id="delivery-area-outside" type="radio" name="delivery_area" value="outside" x-model="area" class="h-4 w-4" required @checked($defaultDeliveryArea === 'outside')>
                        </label>
                    </div>
                    @error('delivery_area') <span id="checkout-delivery-area-error" class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </fieldset>

                <fieldset
                    id="checkout-payment-method"
                    class="block sm:col-span-2"
                    @if ($errors->has('payment_method') || $errors->has('payment'))
                        aria-invalid="true"
                        aria-describedby="checkout-payment-errors"
                    @endif
                >
                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-200">Payment method</legend>
                    <div class="mt-2 space-y-3">
                        @if ($codEnabled)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm transition" :class="method === 'cod' ? 'border-[var(--storefront-brand)] ring-1 ring-[var(--storefront-brand)]' : 'border-gray-300 dark:border-white/15'">
                                <input id="payment-method-cod" type="radio" name="payment_method" value="cod" x-model="method" class="h-4 w-4" required @checked($defaultPaymentMethod === 'cod')>
                                <span class="font-medium text-gray-900 dark:text-white">Cash on Delivery</span>
                            </label>
                        @endif

                        @if ($hasBkash)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm transition" :class="method === 'manual_bkash' ? 'border-[var(--storefront-brand)] ring-1 ring-[var(--storefront-brand)]' : 'border-gray-300 dark:border-white/15'">
                                <input id="payment-method-bkash" type="radio" name="payment_method" value="manual_bkash" x-model="method" class="h-4 w-4" required @checked($defaultPaymentMethod === 'manual_bkash')>
                                <span class="font-medium text-gray-900 dark:text-white">bKash (Send Money)</span>
                            </label>
                        @endif

                        @if ($hasNagad)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm transition" :class="method === 'manual_nagad' ? 'border-[var(--storefront-brand)] ring-1 ring-[var(--storefront-brand)]' : 'border-gray-300 dark:border-white/15'">
                                <input id="payment-method-nagad" type="radio" name="payment_method" value="manual_nagad" x-model="method" class="h-4 w-4" required @checked($defaultPaymentMethod === 'manual_nagad')>
                                <span class="font-medium text-gray-900 dark:text-white">Nagad (Send Money)</span>
                            </label>
                        @endif

                        @if ($hasBkash || $hasNagad)
                            <div x-show="method === 'manual_bkash' || method === 'manual_nagad'" x-cloak class="ml-1 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm dark:border-white/10 dark:bg-white/5">
                                @if ($hasBkash)
                                    <div id="bkash-payment-instructions" x-show="method === 'manual_bkash'">
                                        <p class="text-gray-700 dark:text-gray-200">Send Money to <span class="font-semibold">{{ $setting->manual_bkash_number }}</span></p>
                                        @if ($setting->manual_bkash_instructions)
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $setting->manual_bkash_instructions }}</p>
                                        @endif
                                    </div>
                                @endif
                                @if ($hasNagad)
                                    <div id="nagad-payment-instructions" x-show="method === 'manual_nagad'">
                                        <p class="text-gray-700 dark:text-gray-200">Send Money to <span class="font-semibold">{{ $setting->manual_nagad_number }}</span></p>
                                        @if ($setting->manual_nagad_instructions)
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $setting->manual_nagad_instructions }}</p>
                                        @endif
                                    </div>
                                @endif
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300" x-text="method === 'manual_bkash' ? 'Your bKash number' : 'Your Nagad number'">Your sender number</span>
                                        <input id="checkout-sender-number" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" type="tel" inputmode="tel" name="sender_number" autocomplete="tel" value="{{ old('sender_number') }}" x-bind:required="method === 'manual_bkash' || method === 'manual_nagad'" @error('sender_number') aria-invalid="true" aria-describedby="checkout-sender-number-error" @enderror>
                                        @error('sender_number') <span id="checkout-sender-number-error" class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Transaction ID</span>
                                        <input id="checkout-transaction-id" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" name="trx_id" autocomplete="off" spellcheck="false" value="{{ old('trx_id') }}" x-bind:required="method === 'manual_bkash' || method === 'manual_nagad'" @error('trx_id') aria-invalid="true" aria-describedby="checkout-transaction-id-error" @enderror>
                                        @error('trx_id') <span id="checkout-transaction-id-error" class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>
                    @unless ($hasPaymentPath)
                        <p id="checkout-payment-unavailable" class="mt-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300" role="alert">
                            No payment method is available right now. Please contact the store before placing this order.
                        </p>
                    @endunless
                    @if ($errors->has('payment_method') || $errors->has('payment'))
                        <div id="checkout-payment-errors" class="mt-2 space-y-1 text-sm text-red-600 dark:text-red-400" role="alert">
                            @error('payment_method') <p>{{ $message }}</p> @enderror
                            @error('payment') <p>{{ $message }}</p> @enderror
                        </div>
                    @endif
                </fieldset>

                <label class="block sm:col-span-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Order note <span class="text-gray-400">(optional)</span></span>
                    <textarea id="checkout-note" class="mt-2 min-h-24 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-950 outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/15 dark:bg-white/10 dark:text-white" name="note" autocomplete="off" @error('note') aria-invalid="true" aria-describedby="checkout-note-error" @enderror>{{ old('note') }}</textarea>
                    @error('note') <span id="checkout-note-error" class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>
        </form>

        <aside class="h-fit rounded-xl border border-gray-200 bg-white p-6 lg:sticky lg:top-24 dark:border-white/10 dark:bg-white/5">
            <h2 class="text-lg font-semibold">Order summary</h2>
            <div class="mt-5 space-y-3">
                @foreach ($items as $item)
                    <div class="flex justify-between gap-4 text-sm">
                        <span class="min-w-0 flex-1 truncate text-gray-600 dark:text-gray-300">{{ $item['product']->name }}{{ ($item['variant'] ?? null) ? ' ('.$item['variant']->label().')' : '' }} &times; {{ $item['quantity'] }}</span>
                        <span class="shrink-0 font-semibold">BDT {{ number_format($item['subtotal'], 2) }}</span>
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
                    <span x-text="'BDT ' + deliveryCharge.toFixed(2)" aria-live="polite"></span>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-2 text-lg font-semibold text-gray-950 dark:border-white/10 dark:text-white">
                    <span>Total</span>
                    <span x-text="'BDT ' + total.toFixed(2)" aria-live="polite"></span>
                </div>
            </div>
            @if (($advanceDue ?? 0) > 0)
                <div class="mt-4 rounded-lg border border-[var(--storefront-brand)]/30 bg-[var(--storefront-brand)]/5 px-4 py-3 text-sm leading-6 text-gray-700 dark:text-gray-200">
                    <div class="flex justify-between font-semibold">
                        <span>Advance payable online now</span>
                        <span>BDT {{ number_format($advanceDue, 2) }}</span>
                    </div>
                    @if ($onlinePaymentAvailable ?? false)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Your cart includes pre-order items. You will be redirected to a secure payment page after placing the order.
                        </p>
                    @else
                        <p id="checkout-preorder-payment-unavailable" class="mt-2 text-xs font-medium text-red-600 dark:text-red-400" role="alert">
                            Online payment is currently unavailable. Please contact the store to complete this pre-order.
                        </p>
                    @endif
                </div>
            @endif

            <button
                class="mt-6 w-full rounded-lg bg-[var(--storefront-brand)] px-6 py-3 text-sm font-medium text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                type="submit"
                form="checkout-form"
                data-checkout-submit
                @disabled($checkoutBlocked)
                @if ($checkoutBlocked) aria-disabled="true" aria-describedby="{{ $checkoutBlockDescription }}" @endif
            >
                Place order
            </button>
        </aside>
    </section>

    <script>
        (function () {
            var form = document.getElementById('checkout-form');
            var submitButton = document.querySelector('[data-checkout-submit]');
            if (! form) return;

            var dirty = false;
            form.addEventListener('input', function () { dirty = true; });

            var errorSummary = document.querySelector('[data-checkout-errors]');
            if (errorSummary) {
                window.requestAnimationFrame(function () { errorSummary.focus(); });
            }

            form.addEventListener('submit', function () {
                dirty = false;
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Placing order…';
                }
            });

            window.addEventListener('beforeunload', function (event) {
                if (! dirty) return;
                event.preventDefault();
                event.returnValue = '';
            });
        })();
    </script>
@endsection
