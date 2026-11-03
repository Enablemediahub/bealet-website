@extends('admin.layout')

@section('title', 'Orders | Administration')

@section('admin-content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Administration</span><h1 class="h2 fw-bold">Orders</h1>
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-6"><label class="visually-hidden" for="order-search">Search orders</label><input id="order-search" class="form-control" name="search" value="{{ request('search') }}" placeholder="Tracking code, customer, email"></div>
            <div class="col-md-4"><label class="visually-hidden" for="order-status">Status</label><select class="form-select" id="order-status" name="status"><option value="">All statuses</option>@foreach (['pending','processing','shipped','delivered','cancelled','refunded'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive account-card"><table class="table align-middle mb-0">
            <thead><tr><th>Tracking</th><th>Customer</th><th>Date</th><th>Total</th><th>Payment</th><th>Order</th><th></th></tr></thead>
            <tbody>@forelse ($orders as $order)
                <tr><td>{{ $order->tracking_code }}</td><td>{{ $order->user?->name ?? $order->guest_name ?? 'Guest' }}<div class="small text-muted">{{ $order->user?->email ?? $order->guest_email }}</div></td><td>{{ $order->created_at?->format('M j, Y') }}</td><td>GH₵ {{ number_format((float) $order->total_amount, 2) }}</td><td>{{ \Illuminate\Support\Str::headline($order->payment_status) }}</td><td>{{ \Illuminate\Support\Str::headline($order->status) }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.orders.show', $order) }}">View</a></td></tr>
            @empty<tr><td colspan="7" class="text-center text-muted py-4">No matching orders.</td></tr>@endforelse</tbody>
        </table></div><div class="mt-3">{{ $orders->links() }}</div><a class="btn btn-link" href="{{ route('admin.dashboard') }}">Back to admin</a>
    </section>
@endsection
