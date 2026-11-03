@extends('admin.layout')

@section('title', 'Order ' . $order->tracking_code . ' | Administration')

@section('admin-content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Order management</span><h1 class="h2 fw-bold">Order {{ $order->tracking_code }}</h1>
        @include('admin._feedback')
        <div class="row g-4">
            <div class="col-lg-7">
                <article class="account-card mb-4"><h2 class="h5">Items</h2>
                    @foreach ($order->items as $item)<div class="d-flex justify-content-between border-bottom py-2"><span>{{ $item->product_name }} × {{ $item->quantity }}</span><strong>GH₵ {{ number_format((float) $item->product_price * $item->quantity, 2) }}</strong></div>@endforeach
                    <div class="d-flex justify-content-between pt-3"><strong>Total</strong><strong>GH₵ {{ number_format((float) $order->total_amount, 2) }}</strong></div>
                </article>
                @if ($order->prescriptions->isNotEmpty())
                    <article class="account-card mb-4"><h2 class="h5">Prescription details</h2>@foreach ($order->prescriptions as $prescription)<p class="small mb-1">{{ $prescription->manual_prescription ?: $prescription->customer_notes ?: 'Prescription attached.' }}</p>@if ($prescription->file_path)<p class="small text-muted">Attached file: {{ basename($prescription->file_path) }}</p>@endif @endforeach</article>
                @endif
                <article class="account-card"><h2 class="h5">Fulfillment history</h2>@forelse ($history as $entry)<p class="mb-2"><strong>{{ \Illuminate\Support\Str::headline($entry->status) }}</strong> · {{ $entry->created_at?->format('M j, Y g:i A') }} @if($entry->notes)<span class="text-muted"> — {{ $entry->notes }}</span>@endif</p>@empty<p class="text-muted mb-0">No tracking events yet.</p>@endforelse</article>
            </div>
            <div class="col-lg-5">
                <article class="account-card mb-4"><h2 class="h5">Customer and delivery</h2><p class="mb-1">{{ $order->user?->name ?? $order->guest_name ?? 'Guest' }}</p><p class="mb-1">{{ $order->user?->email ?? $order->guest_email }}</p><p class="mb-1">{{ $order->order_phone ?? $order->guest_phone }}</p><p class="mb-0">{{ $order->shipping_address }}, {{ $order->shipping_city }}, {{ $order->shipping_state }}</p><hr><p class="mb-1">Payment: <strong>{{ \Illuminate\Support\Str::headline($order->payment_status) }}</strong></p><p class="mb-0">Order status: <strong>{{ \Illuminate\Support\Str::headline($order->status) }}</strong></p></article>
                <form class="account-card" method="POST" action="{{ route('admin.orders.update', $order) }}">
                    @csrf @method('PATCH')
                    <h2 class="h5">Fulfillment</h2>
                    <label class="form-label" for="order-status">Status</label>
                    <select class="form-select mb-3" id="order-status" name="status" required>
                        @foreach (['processing','shipped','delivered'] as $status)<option value="{{ $status }}" @selected(old('status', $order->status) === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>@endforeach
                    </select>
                    <label class="form-label" for="tracking-number">Tracking number (required to ship)</label><input class="form-control mb-3" id="tracking-number" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}">
                    <label class="form-label" for="courier-name">Courier</label><input class="form-control mb-3" id="courier-name" name="courier_name" value="{{ old('courier_name', $order->courier_name) }}">
                    <label class="form-label" for="estimated-delivery">Estimated delivery</label><input class="form-control mb-3" id="estimated-delivery" name="estimated_delivery" type="date" value="{{ old('estimated_delivery', $order->estimated_delivery?->format('Y-m-d')) }}">
                    <label class="form-label" for="admin-notes">Internal notes</label><textarea class="form-control mb-3" id="admin-notes" name="admin_notes" rows="3">{{ old('admin_notes', $order->admin_notes) }}</textarea>
                    <button class="btn btn-primary rounded-pill" type="submit" @disabled($order->payment_status !== 'paid')>Save fulfillment</button>
                    @if ($order->payment_status !== 'paid')<p class="small text-muted mt-2 mb-0">Payment must be verified before an order can enter fulfillment.</p>@endif
                </form>
            </div>
        </div><a class="btn btn-link mt-3" href="{{ route('admin.orders.index') }}">Back to orders</a>
    </section>
@endsection
