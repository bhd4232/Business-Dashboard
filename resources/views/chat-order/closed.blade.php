@extends('chat-order.layout')

@section('title', 'লিংকটি আর সক্রিয় নেই')

@section('content')
    <h1>{{ $link->company?->name }}</h1>
    @if ($link->converted_order_id)
        <p style="margin-top: .75rem;">এই লিংক দিয়ে ইতিমধ্যে একটি অর্ডার সম্পন্ন হয়েছে। ধন্যবাদ!</p>
        <p class="muted" style="margin-top: .5rem;">অর্ডার নম্বর: {{ $link->convertedOrder?->order_number }}</p>
    @else
        <p style="margin-top: .75rem;">দুঃখিত, এই অর্ডার লিংকটির মেয়াদ শেষ হয়ে গেছে।</p>
        <p class="muted" style="margin-top: .5rem;">নতুন লিংকের জন্য আমাদের সাথে চ্যাটে যোগাযোগ করুন।</p>
    @endif
@endsection
