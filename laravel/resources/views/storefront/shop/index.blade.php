@extends('storefront.layout')

@section('title', 'Shop Eyewear | ' . config('app.name'))

@section('content')
    <section class="storefront-page-heading">
        <div class="container-lg">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Shop</li>
                </ol>
            </nav>
            <h1 class="mb-2">Our Collections</h1>
            <p class="text-muted mb-0">Browse our premium selection of eyewear</p>
        </div>
    </section>

    <section class="container-lg storefront-section storefront-shop-section">
        <div class="row g-4 align-items-start">
            <aside class="col-lg-3" aria-label="Product filters">
                <div class="card storefront-filter-card">
                    <div class="card-header">
                        <h2 class="h5 mb-0"><i class="fa-solid fa-filter me-2" aria-hidden="true"></i>Filters</h2>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('shop.index') }}" method="GET">
                            <div class="mb-3">
                                <label class="form-label" for="catalogSearch">Search Products</label>
                                <input class="form-control" id="catalogSearch" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="catalogCategory">Category</label>
                                <select class="form-select" id="catalogCategory" name="category">
                                    <option value="">All categories</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ \Illuminate\Support\Str::headline($category) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="catalogAudience">Frame audience</label>
                                <select class="form-select" id="catalogAudience" name="frame_target">
                                    <option value="">All audiences</option>
                                    @foreach ($frameTargets as $frameTarget)
                                        <option value="{{ $frameTarget }}" @selected(($filters['frame_target'] ?? '') === $frameTarget)>{{ \Illuminate\Support\Str::headline($frameTarget) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="catalogBrand">Brand</label>
                                <select class="form-select" id="catalogBrand" name="brand">
                                    <option value="">All brands</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand }}" @selected(($filters['brand'] ?? '') === $brand)>{{ $brand }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Price range</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="visually-hidden" for="catalogMinPrice">Minimum price</label>
                                        <input class="form-control" id="catalogMinPrice" type="number" min="0" step="0.01" name="min_price" value="{{ $filters['min_price'] ?? '' }}" placeholder="Min">
                                    </div>
                                    <div class="col-6">
                                        <label class="visually-hidden" for="catalogMaxPrice">Maximum price</label>
                                        <input class="form-control" id="catalogMaxPrice" type="number" min="0" step="0.01" name="max_price" value="{{ $filters['max_price'] ?? '' }}" placeholder="Max">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="catalogSort">Sort by</label>
                                <select class="form-select" id="catalogSort" name="sort">
                                    <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest</option>
                                    <option value="featured" @selected(($filters['sort'] ?? '') === 'featured')>Featured</option>
                                    <option value="price_low" @selected(($filters['sort'] ?? '') === 'price_low')>Price: low to high</option>
                                    <option value="price_high" @selected(($filters['sort'] ?? '') === 'price_high')>Price: high to low</option>
                                    <option value="popular" @selected(($filters['sort'] ?? '') === 'popular')>Popular</option>
                                </select>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" id="tryOnReady" type="checkbox" name="try_on" value="ready" @checked(($filters['try_on'] ?? '') === 'ready')>
                                <label class="form-check-label" for="tryOnReady">Virtual try-on ready</label>
                            </div>
                            <button class="btn btn-primary w-100" type="submit">Apply filters</button>
                            <a class="btn btn-outline-secondary w-100 mt-2" href="{{ route('shop.index') }}">Clear filters</a>
                        </form>
                    </div>
                </div>
            </aside>

            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 storefront-results-heading">
                    <h2 class="h5 mb-0">The collection</h2>
                    <span class="text-muted">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}</span>
                </div>

                @if ($products->isNotEmpty())
                    <div class="row g-4">
                        @foreach ($products as $product)
                            @include('storefront.shop._product-card', ['product' => $product])
                        @endforeach
                    </div>
                    <div class="mt-4">{{ $products->links('pagination::bootstrap-5') }}</div>
                @else
                    <div class="storefront-empty">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <h2 class="h5">No products match those filters</h2>
                        <p>Try a different search or clear the filters to see the full collection.</p>
                        <a href="{{ route('shop.index') }}">Clear filters</a>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
