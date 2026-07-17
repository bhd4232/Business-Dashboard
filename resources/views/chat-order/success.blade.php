@extends('chat-order.layout')

@section('title', 'অর্ডার সম্পন্ন হয়েছে')

@section('content')
    <h1>ধন্যবাদ! 🎉</h1>
    <p style="margin-top: .75rem;">আপনার অর্ডারটি গ্রহণ করা হয়েছে।</p>
    <p style="margin-top: .5rem;">অর্ডার নম্বর: <strong>{{ $order->order_number }}</strong></p>
    <p class="muted" style="margin-top: .75rem;">{{ $link->company?->name }} টিম শীঘ্রই আপনার সাথে যোগাযোগ করবে। পেমেন্ট: ক্যাশ অন ডেলিভারি।</p>
@endsection
