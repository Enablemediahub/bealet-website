@extends('storefront.layout')

@section('title', $product->name . ' | ' . config('app.name'))

@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 160, ''))
@section('og_title', $product->name . ' | ' . config('app.name'))
@section('og_type', 'product')
@section('og_image', $product->image_url)
@section('og_url', route('shop.show', $product->slug))

@section('content')
    @php
        $productWhatsAppUrl = \App\Support\WhatsAppLink::productInquiry(
            $product,
            $siteSettings['whatsapp_phone'] ?? null,
            $siteSettings['company_name'] ?? config('app.name'),
        );
    @endphp
    <section class="container-lg storefront-section storefront-detail-section">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('shop.index') }}">Shop</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $product->name }}</li>
            </ol>
        </nav>

        <div class="row g-5 align-items-start">
            <div class="col-lg-7">
                <div class="storefront-gallery">
                    @foreach ($product->gallery_urls as $index => $imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ $product->name }}{{ $index > 0 ? ' image ' . ($index + 1) : '' }}" @if ($index > 0) loading="lazy" @endif>
                    @endforeach
                </div>
            </div>
            <div class="col-lg-5">
                <span class="storefront-eyebrow storefront-eyebrow-dark">{{ \Illuminate\Support\Str::headline($product->category) }}</span>
                <h1 class="storefront-detail-title">{{ $product->name }}</h1>
                @if ($product->brand)
                    <p class="text-muted mb-2">{{ $product->brand }}</p>
                @endif
                <p class="storefront-detail-price">Price on enquiry</p>
                <p class="text-muted">Contact our team for current pricing and availability.</p>

                <div class="storefront-detail-stock">
                    <i class="fa-solid fa-circle {{ $product->stock > 0 ? 'text-success' : 'text-secondary' }}"></i>
                    {{ $product->stock > 0 ? 'In stock' : 'Availability on enquiry' }}
                </div>

                <dl class="storefront-product-specs">
                    @foreach ([
                        'Audience' => $product->frame_target,
                        'Material' => $product->frame_material,
                        'Colour' => $product->frame_color,
                        'Shape' => $product->frame_shape,
                    ] as $label => $value)
                        @if ($value)
                            <div><dt>{{ $label }}</dt><dd>{{ \Illuminate\Support\Str::headline($value) }}</dd></div>
                        @endif
                    @endforeach
                </dl>

                @if ($product->description)
                    <div class="storefront-description">
                        <h2 class="h5">Product details</h2>
                        <p>{{ $product->description }}</p>
                    </div>
                @endif

                @if ($product->ar_model_2d || $product->ar_model_2d_left || $product->ar_model_2d_right || $product->ar_model_3d)
                    <p class="storefront-tryon-note"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>This frame is ready for virtual try-on. <a href="{{ route('tryon.index', ['frame' => $product->id]) }}">Try it on</a></p>
                @endif
                <a class="btn btn-success rounded-pill px-4 mb-3" href="{{ $productWhatsAppUrl ?? route('contact.index') }}" @if($productWhatsAppUrl) target="_blank" rel="noopener noreferrer" @endif>
                    <i class="fa-brands fa-whatsapp me-2" aria-hidden="true"></i>Ask on WhatsApp
                </a>
                <a href="{{ route('shop.index') }}" class="btn btn-outline-primary rounded-pill">Back to the collection</a>
            </div>
        </div>
    </section>

    <section class="container-lg storefront-section">
        <div class="storefront-section-heading">
            <div><span class="storefront-eyebrow storefront-eyebrow-dark">Customer feedback</span><h2>Reviews for {{ $product->name }}</h2></div>
            <span class="text-muted">{{ number_format((float) $reviewSummary->average_rating, 1) }}/5 · {{ $reviewSummary->total_reviews }} reviews</span>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        <div class="row g-3">
            @forelse ($reviews as $review)
                <div class="col-md-6">
                    <article class="account-card h-100">
                        <p class="text-warning mb-2" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</p>
                        <p>{{ $review->comment }}</p>
                        @if ($review->review_image)
                            <img class="content-card-image mb-2" src="{{ \App\Support\ContentAsset::url($review->review_image) }}" alt="Photo from {{ $review->reviewer_name ?: 'customer' }}" loading="lazy">
                        @endif
                        <strong>{{ $review->reviewer_name ?: $review->user?->name ?: 'Customer' }}</strong>
                        <p class="small text-muted mb-0">{{ $review->updated_at?->format('M j, Y') }}</p>
                    </article>
                </div>
            @empty
                <div class="col-12"><p class="text-muted">No approved reviews yet. Be the first to share your experience.</p></div>
            @endforelse
        </div>
        <div class="mt-3">{{ $reviews->links() }}</div>
        <div class="account-card mt-4">
            <h3 class="h5 fw-bold">Write a product review</h3>
            <p class="text-muted">Reviews are moderated before publication.</p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('product-reviews.store') }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                @guest
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="product-review-name">Name</label><input class="form-control" id="product-review-name" name="guest_name" value="{{ old('guest_name') }}" required></div>
                        <div class="col-md-6"><label class="form-label" for="product-review-email">Email</label><input class="form-control" id="product-review-email" name="guest_email" type="email" value="{{ old('guest_email') }}" required></div>
                    </div>
                @endguest
                <div class="row g-3 mt-1">
                    <div class="col-md-3"><label class="form-label" for="product-review-rating">Rating</label><select class="form-select" id="product-review-rating" name="rating">@for ($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}">{{ $rating }} / 5</option>@endfor</select></div>
                    <div class="col-md-9"><label class="form-label" for="product-review-comment">Review</label><textarea class="form-control" id="product-review-comment" name="comment" rows="3" minlength="12" maxlength="1500" required>{{ old('comment') }}</textarea></div>
                </div>
                <div class="mt-3"><label class="form-label" for="product-review-image">Photo <span class="text-muted">(optional; JPG, PNG, or WebP, max 5 MB)</span></label><input class="form-control" id="product-review-image" name="review_image" type="file" accept="image/jpeg,image/png,image/webp"></div>
                <button class="btn btn-primary rounded-pill mt-3" type="submit">Submit review</button>
            </form>
        </div>
    </section>

    @if ($relatedProducts->isNotEmpty())
        <section class="container-lg storefront-section storefront-related-section">
            <div class="storefront-section-heading">
                <div><span class="storefront-eyebrow storefront-eyebrow-dark">Keep exploring</span><h2>More in {{ \Illuminate\Support\Str::headline($product->category) }}</h2></div>
                <a class="storefront-text-link" href="{{ route('shop.index', ['category' => $product->category]) }}">View category <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
            <div class="row g-4">
                @foreach ($relatedProducts as $relatedProduct)
                    @include('storefront.shop._product-card', ['product' => $relatedProduct])
                @endforeach
            </div>
        </section>
    @endif
@endsection
