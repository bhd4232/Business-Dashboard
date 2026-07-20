@extends('storefront.layout')

@php
    $profileUpdateUrl = route('storefront.account.profile.update');
    $passwordUpdateUrl = route('storefront.account.password.update');
    $ordersUrl = route('storefront.account.orders');
    $resellerUrl = route('storefront.reseller.show');
    $resellerStatusLabel = \App\Models\Customer::RESELLER_STATUSES[$customer->reseller_status] ?? null;
@endphp

@section('content')
    <section class="mx-auto w-full max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-[var(--storefront-brand)]">My account</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Hi, {{ $customer->name }}.</h1>
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            Member since {{ $customer->created_at->format('d M, Y') }} &middot;
            <a class="font-medium text-[var(--storefront-brand)]" href="{{ $ordersUrl }}">View my orders</a>
        </p>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                <h2 class="text-lg font-semibold tracking-tight">Profile information</h2>
                <form class="mt-5 space-y-5" method="POST" action="{{ $profileUpdateUrl }}">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="text-xs font-medium text-gray-500" for="name">Name</label>
                        <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="name" name="name" type="text" autocomplete="name" value="{{ old('name', $customer->name) }}" required>
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500" for="phone_display">Phone number</label>
                        <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-200 bg-gray-50 px-4 text-sm text-gray-500 outline-none dark:border-white/10 dark:bg-white/5 dark:text-gray-400" id="phone_display" type="tel" value="{{ $customer->phone }}" disabled readonly>
                        <p class="mt-1 text-xs text-gray-400">Your login phone number. Contact support to change it.</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500" for="email">Email</label>
                        <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="email" name="email" type="email" autocomplete="email" spellcheck="false" value="{{ old('email', $customer->email) }}">
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500" for="address">Default delivery address</label>
                        <textarea class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="address" name="address" rows="3" autocomplete="street-address">{{ old('address', $customer->address) }}</textarea>
                        @error('address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-gray-400">Used to pre-fill checkout.</p>
                    </div>
                    <button class="rounded-lg bg-[var(--storefront-brand)] px-6 py-2.5 text-sm font-medium text-white transition hover:opacity-90 [touch-action:manipulation]" type="submit">
                        Save changes
                    </button>
                </form>
            </div>

            <div class="flex flex-col gap-6">
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                    <h2 class="text-lg font-semibold tracking-tight">Change password</h2>
                    <form class="mt-5 space-y-5" method="POST" action="{{ $passwordUpdateUrl }}">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="text-xs font-medium text-gray-500" for="current_password">Current password</label>
                            <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                            @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500" for="new_password">New password</label>
                            <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="new_password" name="password" type="password" autocomplete="new-password" minlength="8" required aria-describedby="new_password_hint">
                            <p class="mt-1 text-xs text-gray-400" id="new_password_hint">At least 8 characters.</p>
                            @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500" for="new_password_confirmation">Confirm new password</label>
                            <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="new_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                        </div>
                        <button class="rounded-lg border border-gray-300 px-6 py-2.5 text-sm font-medium text-gray-900 transition hover:border-gray-950 [touch-action:manipulation] dark:border-white/15 dark:text-white dark:hover:border-white" type="submit">
                            Update password
                        </button>
                    </form>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                    <h2 class="text-lg font-semibold tracking-tight">Reseller status</h2>
                    @if ($resellerStatusLabel && $customer->reseller_status !== 'none')
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $resellerStatusLabel }}</p>
                    @else
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Get wholesale pricing and direct ordering support.</p>
                        <a class="mt-4 inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-900 transition hover:border-gray-950 dark:border-white/15 dark:text-white dark:hover:border-white" href="{{ $resellerUrl }}">
                            Apply to become a reseller
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
