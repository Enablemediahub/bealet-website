@extends('admin.layout')

@section('title', 'Products | Administration')

@section('admin-content')
    <section class="container-lg account-page">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><span class="storefront-eyebrow storefront-eyebrow-dark">Administration</span><h1 class="h2 fw-bold mb-0">Products</h1></div>
            <a class="btn btn-primary rounded-pill" href="{{ route('admin.products.create') }}">Add product</a>
        </div>
        @include('admin._feedback')
        <form class="row g-2 mb-3" method="GET">
            <div class="col-sm-8"><label class="visually-hidden" for="product-search">Search products</label><input id="product-search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search name or SKU"></div>
            <div class="col-sm-4"><button class="btn btn-outline-primary w-100" type="submit">Search</button></div>
        </form>
        <div class="table-responsive account-card">
            <table class="table align-middle mb-0">
                <thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Stock</th><th>Visibility</th><th></th></tr></thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td><div class="d-flex align-items-center gap-2"><img src="{{ $product->image_url }}" alt="" width="52" height="52" class="rounded object-fit-cover"><div><strong>{{ $product->name }}</strong><div class="small text-muted">{{ $product->brand }}</div></div></div></td>
                            <td>{{ $product->sku ?: '—' }}</td><td>GH₵ {{ number_format((float) $product->price, 2) }}</td>
                            <td><span class="{{ $product->stock <= $product->low_stock_threshold ? 'text-danger fw-semibold' : '' }}">{{ $product->stock }}</span></td>
                            <td>{{ $product->is_active ? 'Active' : 'Hidden' }}</td>
                            <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.products.edit', $product) }}">Edit</a>
                                @if ($product->is_active)<form class="d-inline" method="POST" action="{{ route('admin.products.deactivate', $product) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger" type="submit">Hide</button></form>@endif
                            </td>
                        </tr>
                    @empty<tr><td colspan="6" class="text-center text-muted py-4">No products match your search.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $products->links() }}</div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-link mt-2">Back to admin</a>
    </section>
@endsection
