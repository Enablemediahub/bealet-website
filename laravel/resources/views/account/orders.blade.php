@extends('storefront.layout')

@section('title', 'My orders | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Your purchases</span>
        <h1 class="h2 fw-bold mb-4">My orders</h1>
        @if ($orders->isEmpty())
            <div class="storefront-empty">
                <i class="fa-solid fa-box-open"></i>
                <h2 class="h5">No orders yet</h2>
                <p>When you place an order, its progress will appear here.</p>
                <a href="{{ route('shop.index') }}">Browse eyewear</a>
            </div>
        @else
            <div class="table-responsive account-card">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Tracking code</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->tracking_code }}</td>
                                <td>{{ $order->created_at?->format('M j, Y') }}</td>
                                <td>{{ $order->items_count }}</td>
                                <td>{{ \App\Support\Money::formatGhs(\App\Support\Money::toPesewas($order->total_amount)) }}</td>
                                <td><span class="badge text-bg-{{ $order->payment_status === 'paid' ? 'success' : ($order->payment_status === 'failed' ? 'danger' : 'warning') }}">{{ \Illuminate\Support\Str::headline($order->payment_status) }}</span></td>
                                <td><a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('account.orders.show', $order) }}">Details</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $orders->links() }}</div>
        @endif
    </section>
@endsection
