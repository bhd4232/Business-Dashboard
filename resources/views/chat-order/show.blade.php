@extends('chat-order.layout')

@section('title', 'অর্ডার কনফার্ম করুন')

@section('content')
    <h1>{{ $link->company?->name }}</h1>
    <p class="muted">নিচের তথ্যগুলো দেখে অর্ডার কনফার্ম করুন।</p>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('chat-order.store', $link->token) }}">
        @csrf
        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">

        <div style="margin-top: 1rem;">
            @php($total = 0)
            @foreach ($link->prefill['items'] ?? [] as $index => $item)
                @php($qty = (int) old("quantities.$index", $item['quantity'] ?? 1))
                @php($total += $qty * (float) $item['unit_price'])
                <div class="item">
                    <span>
                        {{ $item['name'] ?? 'Item' }}
                        @if (! empty($item['variant_label']))
                            <span class="muted">({{ $item['variant_label'] }})</span>
                        @endif
                        <br><span class="muted">৳{{ number_format((float) $item['unit_price'], 2) }}</span>
                    </span>
                    <input type="number" name="quantities[{{ $index }}]" value="{{ $qty }}" min="1" max="1000">
                </div>
            @endforeach
            <div class="total"><span>মোট (ডেলিভারি চার্জ ছাড়া)</span><span>৳{{ number_format($total, 2) }}</span></div>
        </div>

        <label for="name">আপনার নাম</label>
        <input type="text" id="name" name="name" required value="{{ old('name', $link->prefill['name'] ?? '') }}">

        <label for="phone">মোবাইল নম্বর</label>
        <input type="tel" id="phone" name="phone" required value="{{ old('phone', $link->prefill['phone'] ?? '') }}">

        <label for="address">ডেলিভারি ঠিকানা</label>
        <textarea id="address" name="address" rows="3" required>{{ old('address', $link->prefill['address'] ?? '') }}</textarea>

        <button type="submit">অর্ডার কনফার্ম করুন</button>
        <p class="muted" style="margin-top:.75rem; text-align:center;">পেমেন্ট: ক্যাশ অন ডেলিভারি। লিংকের মেয়াদ: {{ $link->expires_at->format('d M Y') }}</p>
    </form>
@endsection
