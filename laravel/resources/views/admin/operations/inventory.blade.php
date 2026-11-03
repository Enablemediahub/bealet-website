@extends('admin.layout')

@section('title', 'Inventory | Administration')

@section('admin-content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Administration</span><h1 class="h2 fw-bold">Inventory</h1>
        @include('admin._feedback')
        <div class="table-responsive account-card"><table class="table align-middle mb-0">
            <thead><tr><th>Product</th><th>On hand</th><th>Low-stock alert</th><th>Save</th></tr></thead>
            <tbody>@foreach ($products as $product)
                <tr><td>{{ $product->name }} <span class="small text-muted">{{ $product->sku }}</span></td>
                    <td colspan="3"><form class="row g-2 align-items-center" method="POST" action="{{ route('admin.inventory.update', $product) }}">
                        @csrf @method('PATCH')
                        <div class="col-4"><label class="visually-hidden" for="stock-{{ $product->id }}">Stock</label><input class="form-control" id="stock-{{ $product->id }}" name="stock" type="number" min="0" max="1000000" value="{{ $product->stock }}" required></div>
                        <div class="col-4"><label class="visually-hidden" for="threshold-{{ $product->id }}">Low-stock threshold</label><input class="form-control" id="threshold-{{ $product->id }}" name="low_stock_threshold" type="number" min="0" max="1000000" value="{{ $product->low_stock_threshold ?? 5 }}" required></div>
                        <div class="col-4"><button class="btn btn-sm btn-outline-primary" type="submit">Update</button></div>
                    </form></td></tr>
            @endforeach</tbody>
        </table></div>
        <div class="mt-3">{{ $products->links() }}</div><a class="btn btn-link" href="{{ route('admin.dashboard') }}">Back to admin</a>
    </section>
@endsection
