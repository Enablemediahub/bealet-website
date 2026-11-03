<div class="col-md-6 col-lg-4">
    <article class="card product-card storefront-product-card h-100">
        @php
            $productImages = $product->gallery_urls;
        @endphp
        @if (($slideshow ?? false) && count($productImages) > 1)
            <div id="featured-product-gallery-{{ $product->id }}" class="carousel slide product-gallery-carousel featured-product-gallery-carousel" data-bs-ride="carousel" data-bs-interval="2400" data-bs-pause="hover" data-bs-wrap="true">
                <span class="product-image-label product-image-label--{{ \Illuminate\Support\Str::slug($product->category) }}">
                    {{ \Illuminate\Support\Str::headline($product->category) }}
                </span>
                <div class="carousel-inner">
                    @foreach ($productImages as $imageIndex => $imageUrl)
                        <div class="carousel-item {{ $imageIndex === 0 ? 'active' : '' }}">
                            <a class="storefront-product-image" href="{{ route('shop.show', $product->slug) }}">
                                <img src="{{ $imageUrl }}" alt="{{ $product->name }} image {{ $imageIndex + 1 }}" loading="{{ $imageIndex === 0 ? 'eager' : 'lazy' }}">
                            </a>
                        </div>
                    @endforeach
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#featured-product-gallery-{{ $product->id }}" data-bs-slide="prev" aria-label="Previous product image">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#featured-product-gallery-{{ $product->id }}" data-bs-slide="next" aria-label="Next product image">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                </button>
                <div class="product-gallery-dots">
                    @foreach ($productImages as $imageIndex => $imageUrl)
                        <button type="button" data-bs-target="#featured-product-gallery-{{ $product->id }}" data-bs-slide-to="{{ $imageIndex }}" class="{{ $imageIndex === 0 ? 'active' : '' }}" aria-label="View product image {{ $imageIndex + 1 }}"></button>
                    @endforeach
                </div>
            </div>
        @else
            <a class="storefront-product-image" href="{{ route('shop.show', $product->slug) }}">
                <span class="product-image-label product-image-label--{{ \Illuminate\Support\Str::slug($product->category) }}">
                    {{ \Illuminate\Support\Str::headline($product->category) }}
                </span>
                <img src="{{ $productImages[0] }}" alt="{{ $product->name }}" loading="lazy">
            </a>
        @endif
        @php
            $productWhatsAppUrl = \App\Support\WhatsAppLink::productInquiry(
                $product,
                $siteSettings['whatsapp_phone'] ?? null,
                $siteSettings['company_name'] ?? config('app.name'),
            );
        @endphp
        <div class="product-info storefront-product-info">
            @if ($product->brand)
                <p class="storefront-product-brand">{{ $product->brand }}</p>
            @endif
            <a class="product-name storefront-product-name" href="{{ route('shop.show', $product->slug) }}">{{ $product->name }}</a>
            <p class="product-price text-muted mb-3">Price on enquiry</p>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge text-bg-light border">{{ \Illuminate\Support\Str::headline($product->category) }}</span>
                @if ($product->frame_target)
                    <span class="badge text-bg-light border">{{ \Illuminate\Support\Str::headline($product->frame_target) }}</span>
                @endif
            </div>
            <div class="storefront-product-actions mt-auto">
                <a class="storefront-product-action storefront-product-action--details" href="{{ route('shop.show', $product->slug) }}">
                    <i class="fa-regular fa-eye" aria-hidden="true"></i><span>View details</span>
                </a>
                <a class="storefront-product-action storefront-product-action--whatsapp" href="{{ $productWhatsAppUrl ?? route('contact.index') }}" @if($productWhatsAppUrl) target="_blank" rel="noopener noreferrer" @endif>
                    <i class="fa-brands fa-whatsapp" aria-hidden="true"></i><span>Ask on WhatsApp</span>
                </a>
            </div>
        </div>
    </article>
</div>
