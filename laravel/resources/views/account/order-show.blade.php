@extends('storefront.layout')

@section('title', 'Order ' . $order->tracking_code . ' | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <a class="small" href="{{ route('account.orders') }}"><i class="fa-solid fa-arrow-left me-1"></i>Back to my orders</a>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mt-3 mb-4">
            <div><span class="storefront-eyebrow storefront-eyebrow-dark">Order details</span><h1 class="h2 fw-bold">{{ $order->tracking_code }}</h1></div>
            <span class="badge text-bg-{{ $order->payment_status === 'paid' ? 'success' : ($order->payment_status === 'failed' ? 'danger' : 'warning') }}">{{ \Illuminate\Support\Str::headline($order->payment_status) }}</span>
        </div>
        <div class="account-card mb-4">
            <div class="d-flex justify-content-between gap-3">
                <span>Order status</span><strong>{{ \Illuminate\Support\Str::headline((string) $order->status) }}</strong>
            </div>
            <div class="d-flex justify-content-between gap-3 mt-2">
                <span>Order date</span><strong>{{ $order->created_at?->format('M j, Y g:i A') }}</strong>
            </div>
            <div class="d-flex justify-content-between gap-3 mt-2">
                <span>Shipping destination</span><strong>{{ $order->shipping_city }}, {{ $order->shipping_state }}, {{ $order->shipping_country }}</strong>
            </div>
        </div>
        <div class="account-card">
            <h2 class="h5 fw-bold mb-3">Items</h2>
            @foreach ($order->items as $item)
                <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                    <span>{{ $item->product_name }} <span class="text-muted">× {{ $item->quantity }}</span></span>
                    <strong>{{ \App\Support\Money::formatGhs(\App\Support\Money::toPesewas($item->product_price) * $item->quantity) }}</strong>
                </div>
            @endforeach
            <div class="d-flex justify-content-between gap-3 pt-3"><strong>Total</strong><strong>{{ \App\Support\Money::formatGhs(\App\Support\Money::toPesewas($order->total_amount)) }}</strong></div>
        </div>
    </section>
@endsection
