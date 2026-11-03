@extends('admin.layout')

@section('title', ($product->exists ? 'Edit product' : 'Add product') . ' | Administration')

@section('admin-content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Administration</span>
        <h1 class="h2 fw-bold">{{ $product->exists ? 'Edit product' : 'Add product' }}</h1>
        @include('admin._feedback')
        <form class="account-card" method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
            @csrf
            @if ($product->exists) @method('PUT') @endif
            <div class="row g-3">
                @foreach (['name' => 'Product name', 'slug' => 'URL slug', 'sku' => 'SKU', 'brand' => 'Brand', 'category' => 'Category', 'frame_target' => 'Audience', 'price' => 'Price (GHS)', 'sale_price' => 'Sale price (GHS)', 'stock' => 'Stock', 'low_stock_threshold' => 'Low-stock threshold', 'frame_material' => 'Material', 'frame_color' => 'Color', 'frame_shape' => 'Shape', 'lens_width' => 'Lens width', 'bridge_width' => 'Bridge width', 'temple_length' => 'Temple length', 'lens_type' => 'Lens type', 'lens_coating' => 'Lens coating'] as $name => $label)
                    <div class="col-md-4"><label class="form-label" for="field-{{ $name }}">{{ $label }}</label><input class="form-control" id="field-{{ $name }}" name="{{ $name }}" value="{{ old($name, $product->{$name}) }}" @if (in_array($name, ['price', 'stock', 'low_stock_threshold'])) type="number" step="{{ $name === 'price' ? '0.01' : '1' }}" min="0" @endif @if ($name === 'name' || $name === 'category' || $name === 'price' || $name === 'stock' || $name === 'low_stock_threshold') required @endif></div>
                @endforeach
                <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $product->description) }}</textarea></div>
                @foreach (['ar_position_x' => 'Try-on horizontal offset', 'ar_position_y' => 'Try-on vertical offset', 'ar_scale' => 'Try-on scale'] as $name => $label)
                    <div class="col-md-4"><label class="form-label" for="field-{{ $name }}">{{ $label }}</label><input class="form-control" id="field-{{ $name }}" name="{{ $name }}" type="number" step="{{ $name === 'ar_scale' ? '0.01' : '1' }}" value="{{ old($name, $product->{$name} ?? ($name === 'ar_scale' ? 1 : 0)) }}"></div>
                @endforeach
                <div class="col-md-6"><label class="form-label" for="product_image">Main image</label><input class="form-control" id="product_image" name="product_image" type="file" accept="image/jpeg,image/png,image/webp">@if ($product->main_image)<div class="mt-2"><img src="{{ $product->image_url }}" alt="" width="100" class="rounded"><label class="ms-2"><input type="checkbox" name="remove_product_image" value="1"> Remove image</label></div>@endif</div>
                <div class="col-md-6"><label class="form-label" for="gallery_uploads">Gallery images (up to 4 per upload)</label><input class="form-control" id="gallery_uploads" name="gallery_uploads[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>@if ($product->exists)<label class="small mt-2"><input type="checkbox" name="replace_gallery" value="1"> Replace existing gallery images when uploading</label>@endif</div>
                @foreach (['ar_model_2d' => 'Front try-on PNG/WebP', 'ar_model_2d_left' => 'Left view PNG/WebP', 'ar_model_2d_right' => 'Right view PNG/WebP', 'ar_model_3d' => '3D model (GLB)'] as $name => $label)
                    <div class="col-md-6"><label class="form-label" for="{{ $name }}">{{ $label }}</label><input class="form-control" id="{{ $name }}" name="{{ $name }}" type="file" accept="{{ $name === 'ar_model_3d' ? '.glb,model/gltf-binary' : 'image/png,image/webp' }}">
                        @if ($product->{$name})<p class="small text-muted mt-1">Current: {{ basename($product->{$name}) }} <label class="ms-2"><input type="checkbox" name="remove_{{ $name }}" value="1"> Remove</label></p>@endif
                    </div>
                @endforeach
                <div class="col-12 d-flex flex-wrap gap-4">
                    <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->exists ? $product->is_active : true))> Active on storefront</label>
                    <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured || $product->featured))> Featured</label>
                </div>
            </div>
            <button class="btn btn-primary rounded-pill mt-4" type="submit">Save product</button>
            <a class="btn btn-outline-secondary rounded-pill mt-4" href="{{ route('admin.products.index') }}">Cancel</a>
        </form>
    </section>
@endsection
