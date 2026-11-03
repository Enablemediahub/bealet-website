@extends('storefront.layout')

@section('title', 'Order ' . $order->tracking_code . ' | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <div class="checkout-pending-card account-card">
            @if ($order->payment_status === 'paid')
                <span class="order-confirmation-icon"><i class="fa-solid fa-check"></i></span>
                <span class="storefront-eyebrow storefront-eyebrow-dark">Payment confirmed</span>
                @if ($order->status === 'pending')
                    <h1 class="h2 fw-bold">Your order needs a final review</h1>
                    <p class="text-muted">Your payment is confirmed. Our team will verify item availability and contact you shortly.</p>
                @else
                    <h1 class="h2 fw-bold">Thank you for your order</h1>
                    <p class="text-muted">Your order is being processed. Keep this tracking code for your records.</p>
                @endif
            @else
                <span class="storefront-eyebrow storefront-eyebrow-dark">Order received</span>
                <h1 class="h2 fw-bold">Payment is still being confirmed</h1>
                <p class="text-muted">Your order is saved. If you have just paid, allow a moment for confirmation.</p>
            @endif
            <div class="order-tracking-code">{{ $order->tracking_code }}</div>
            <div class="d-flex justify-content-between border-top pt-3 mt-4">
                <span>Total</span>
                <strong>{{ \App\Support\Money::formatGhs(\App\Support\Money::toPesewas($order->total_amount)) }}</strong>
            </div>
            @auth
                @if ((int) auth()->id() === (int) $order->user_id)
                    <a class="btn btn-primary rounded-pill w-100 mt-4" href="{{ route('account.orders.show', $order) }}">View order details</a>
                @endif
            @endauth
            <a class="btn btn-outline-primary rounded-pill w-100 mt-3" href="{{ route('shop.index') }}">Continue shopping</a>
        </div>
    </section>
@endsection
