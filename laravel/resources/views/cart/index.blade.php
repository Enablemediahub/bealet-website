@extends('storefront.layout')

@section('title', 'Your cart | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Ready when you are</span>
        <h1 class="h2 fw-bold mb-4">Your cart</h1>
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($items->isEmpty())
            <div class="storefront-empty">
                <i class="fa-solid fa-bag-shopping"></i>
                <h2 class="h5">Your cart is empty</h2>
                <p>Explore the collection and add something you love.</p>
                <a href="{{ route('shop.index') }}">Browse eyewear</a>
            </div>
        @else
            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <div class="account-card">
                        @foreach ($items as $item)
                            <div class="cart-line">
                                <img src="{{ $item->image_url }}" alt="{{ $item->name }}" loading="lazy">
                                <div class="cart-line-copy">
                                    <a class="fw-bold" href="{{ route('shop.show', $item->slug) }}">{{ $item->name }}</a>
                                    <span class="text-muted">{{ \App\Support\Money::formatGhs(\App\Support\Money::toPesewas($item->price)) }} each</span>
                                    <span class="small {{ $item->stock < $item->quantity ? 'text-danger' : 'text-muted' }}">
                                        {{ $item->stock >= $item->quantity ? $item->stock . ' available' : 'Only ' . $item->stock . ' currently available' }}
                                    </span>
                                </div>
                                <form method="POST" action="{{ route('cart.update', $item->cart_id) }}" class="cart-quantity">
                                    @csrf
                                    @method('PATCH')
                                    <label class="visually-hidden" for="quantity-{{ $item->cart_id }}">Quantity</label>
                                    <input class="form-control" id="quantity-{{ $item->cart_id }}" name="quantity" type="number" min="1" max="99" value="{{ $item->quantity }}">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit" aria-label="Update quantity"><i class="fa-solid fa-check"></i></button>
                                </form>
                                <strong>{{ \App\Support\Money::formatGhs($item->line_total_pesewas) }}</strong>
                                <form method="POST" action="{{ route('cart.destroy', $item->cart_id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger" type="submit" aria-label="Remove {{ $item->name }}"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="account-card cart-summary">
                        <h2 class="h5 fw-bold">Order summary</h2>
                        <div class="d-flex justify-content-between mt-3"><span>Subtotal</span><strong>{{ \App\Support\Money::formatGhs($subtotalPesewas) }}</strong></div>
                        <p class="small text-muted mt-2">Shipping is confirmed during checkout. Prices are shown in Ghana cedis.</p>
                        <a class="btn btn-primary rounded-pill w-100" href="{{ route('checkout.index') }}">Continue to checkout</a>
                        <a class="btn btn-link w-100 mt-2" href="{{ route('shop.index') }}">Continue shopping</a>
                    </div>
                </div>
            </div>
        @endif
    </section>
@endsection
