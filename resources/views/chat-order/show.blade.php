@extends('chat-order.layout')

@section('title', 'অর্ডার কনফার্ম করুন')

@section('content')
    <h1>অর্ডার কনফার্ম করুন</h1>
    <p class="muted" style="margin-top: .3rem;">নিচের তথ্যগুলো দেখে নিয়ে অর্ডারটি কনফার্ম করুন।</p>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('chat-order.store', $link->token) }}">
        @csrf
        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">

        <div class="items">
            @php($total = 0)
            @foreach ($link->prefill['items'] ?? [] as $index => $item)
                @php($qty = (int) old("quantities.$index", $item['quantity'] ?? 1))
                @php($total += $qty * (float) $item['unit_price'])
                <div class="item">
                    <span style="min-width: 0; display: flex; align-items: center; gap: .65rem;">
                        @if (! empty($item['image']))
                            <img src="{{ \App\Support\CompanyMedia::publicUrl($item['image'], $link->company) }}" alt="{{ $item['name'] ?? 'Item' }}" style="width: 3.25rem; height: 3.25rem; object-fit: cover; border-radius: .65rem; flex: none;">
                        @endif
                        <span style="min-width: 0;">
                            <span class="item-name">
                                {{ $item['name'] ?? 'Item' }}
                                @if (! empty($item['variant_label']))
                                    <span class="muted">({{ $item['variant_label'] }})</span>
                                @endif
                            </span>
                            <br><span class="item-price">৳{{ number_format((float) $item['unit_price'], 2) }} / পিস</span>
                        </span>
                    </span>
                    <input type="number" name="quantities[{{ $index }}]" value="{{ $qty }}" min="1" max="1000" aria-label="পরিমাণ"
                        class="js-qty" data-price="{{ (float) $item['unit_price'] }}" inputmode="numeric">
                </div>
            @endforeach
            <div class="total">
                <span>মোট (ডেলিভারি চার্জ ছাড়া)</span>
                <span class="amount" id="js-total">৳{{ number_format($total, 2) }}</span>
            </div>
        </div>

        <label for="name">আপনার নাম</label>
        <input type="text" id="name" name="name" required autocomplete="name" value="{{ old('name', $link->prefill['name'] ?? '') }}">

        <label for="phone">মোবাইল নম্বর</label>
        <input type="tel" id="phone" name="phone" required autocomplete="tel" inputmode="tel" value="{{ old('phone', $link->prefill['phone'] ?? '') }}">

        <label for="address">ডেলিভারি ঠিকানা</label>
        <textarea id="address" name="address" rows="3" required autocomplete="street-address" placeholder="বাসা/রোড, এলাকা, শহর">{{ old('address', $link->prefill['address'] ?? '') }}</textarea>

        <button type="submit" class="btn">✓ অর্ডার কনফার্ম করুন</button>
        <p class="muted" style="margin-top: .8rem; text-align: center;">পেমেন্ট: ক্যাশ অন ডেলিভারি · লিংকের মেয়াদ: {{ $link->expires_at->format('d M Y') }}</p>
    </form>

    <script>
        // Live total: quantity change updates the grand total instantly.
        (() => {
            const inputs = document.querySelectorAll('.js-qty');
            const totalEl = document.getElementById('js-total');

            const recalc = () => {
                let total = 0;
                inputs.forEach((input) => {
                    const qty = Math.max(1, parseInt(input.value, 10) || 0);
                    total += qty * parseFloat(input.dataset.price || '0');
                });
                totalEl.textContent = '৳' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };

            inputs.forEach((input) => {
                input.addEventListener('input', recalc);
                input.addEventListener('change', recalc);
            });
        })();
    </script>
@endsection
