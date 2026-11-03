@extends('storefront.layout')

@section('title', 'Complete your payment | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <div class="checkout-pending-card account-card">
            <span class="storefront-eyebrow storefront-eyebrow-dark">Order {{ $order->tracking_code }}</span>
            <h1 class="h2 fw-bold">Complete your payment</h1>
            @if ($errors->any())
                <div class="alert alert-warning" role="alert">{{ $errors->first() }}</div>
            @endif
            <p class="text-muted">Your items are reserved until {{ $order->reservation_expires_at?->format('g:i A') }}. Continue to Paystack to finish securely.</p>
            <div class="d-flex justify-content-between border-top pt-3 mb-4">
                <span>Order total</span>
                <strong>{{ \App\Support\Money::formatGhs(\App\Support\Money::toPesewas($order->total_amount)) }}</strong>
            </div>
            @if ($order->stock_reserved && $order->reservation_expires_at?->isFuture())
                <form method="POST" action="{{ route('checkout.pay', $order) }}">
                    @csrf
                    <button class="btn btn-primary btn-lg rounded-pill w-100" type="submit">Continue to Paystack</button>
                </form>
            @else
                <a class="btn btn-outline-primary rounded-pill w-100" href="{{ route('cart.index') }}">Review your cart</a>
            @endif
            @auth
                <a class="btn btn-link w-100 mt-2" href="{{ route('account.orders') }}">View my orders</a>
            @endauth
        </div>
    </section>
@endsection
