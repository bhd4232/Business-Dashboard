@extends('chat-order.layout')

@section('title', 'অর্ডার সম্পন্ন হয়েছে')

@section('content')
    <div class="success-hero">
        <div class="check">
            <svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
        </div>
        <h1>ধন্যবাদ! অর্ডারটি গ্রহণ করা হয়েছে 🎉</h1>
        <div class="order-no">অর্ডার নম্বর: {{ $order->order_number }}</div>
        <p class="muted" style="margin-top: 1rem;">
            {{ $link->company?->name }} টিম শীঘ্রই আপনার সাথে যোগাযোগ করবে।<br>
            পেমেন্ট: ক্যাশ অন ডেলিভারি।
        </p>
    </div>

    @auth
        <a href="{{ url('/admin/crm/inbox') }}" class="btn" style="margin-top: 1.5rem;">↩ ইনবক্সে ফিরে যান</a>
    @else
        <button type="button" class="btn secondary" style="margin-top: 1.5rem;" onclick="history.length > 1 ? history.back() : window.close();">↩ ফিরে যান</button>
    @endauth
@endsection
