@extends('chat-order.layout')

@section('title', 'লিংকটি আর সক্রিয় নেই')

@section('content')
    @if ($link->converted_order_id)
        <div class="success-hero">
            <div class="check">
                <svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
            </div>
            <h1>এই লিংক দিয়ে ইতিমধ্যে অর্ডার সম্পন্ন হয়েছে</h1>
            <div class="order-no">অর্ডার নম্বর: {{ $link->convertedOrder?->order_number }}</div>
            <p class="muted" style="margin-top: 1rem;">ধন্যবাদ! নতুন অর্ডারের জন্য আমাদের সাথে চ্যাটে যোগাযোগ করুন।</p>
        </div>
    @else
        <h1>দুঃখিত, এই অর্ডার লিংকটির মেয়াদ শেষ 😔</h1>
        <p class="muted" style="margin-top: .6rem;">নতুন লিংকের জন্য আমাদের সাথে চ্যাটে যোগাযোগ করুন।</p>
    @endif

    @auth
        <a href="{{ url('/admin/inbox') }}" class="btn secondary" style="margin-top: 1.5rem;">↩ ইনবক্সে ফিরে যান</a>
    @endauth
@endsection
