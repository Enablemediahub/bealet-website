@extends('storefront.layout')

@section('title', 'Customer reviews | ' . config('app.name'))

@section('content')
    <section class="container-lg account-page">
        <span class="storefront-eyebrow storefront-eyebrow-dark">Customer stories</span>
        <h1 class="h2 fw-bold">Hear from the Bealet community</h1>
        <div class="account-card my-4">
            <strong class="display-6">{{ number_format((float) $summary->average_rating, 1) }}</strong>
            <span class="text-warning ms-2" aria-label="Average rating out of five">★★★★★</span>
            <p class="mb-0 text-muted">{{ $summary->total_reviews }} approved customer testimonials</p>
        </div>
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <div class="row g-3">
            @forelse ($reviews as $review)
                <div class="col-md-6">
                    <article class="account-card h-100">
                        <p class="text-warning mb-2" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</p>
                        <blockquote class="mb-3">{{ $review->comment }}</blockquote>
                        @if ($review->profile_image)
                            <img class="review-avatar mt-2" src="{{ \App\Support\ContentAsset::url($review->profile_image) }}" alt="{{ $review->reviewer_name }}" loading="lazy">
                        @endif
                        <strong>{{ $review->reviewer_name }}</strong>
                        <p class="small text-muted mb-0">{{ $review->updated_at?->format('M j, Y') }}</p>
                    </article>
                </div>
            @empty
                <div class="col-12"><div class="storefront-empty"><p>No approved testimonials yet. Share your experience below.</p></div></div>
            @endforelse
        </div>
        <div class="mt-4">{{ $reviews->links() }}</div>
        <div class="account-card mt-5">
            <h2 class="h4 fw-bold">Share your experience</h2>
            <p class="text-muted">New and updated testimonials are reviewed before appearing publicly.</p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('reviews.store') }}">
                @csrf
                @guest
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="reviewer-name">Name</label><input class="form-control" id="reviewer-name" name="reviewer_name" value="{{ old('reviewer_name') }}" required></div>
                        <div class="col-md-6"><label class="form-label" for="reviewer-email">Email</label><input class="form-control" id="reviewer-email" name="reviewer_email" type="email" value="{{ old('reviewer_email') }}" required></div>
                    </div>
                @endguest
                <div class="mt-3">
                    <label class="form-label" for="review-rating">Rating</label>
                    <select class="form-select" id="review-rating" name="rating" style="max-width: 180px" required>
                        @for ($rating = 5; $rating >= 1; $rating--)
                            <option value="{{ $rating }}" @selected((int) old('rating', 5) === $rating)>{{ $rating }} {{ $rating === 1 ? 'star' : 'stars' }}</option>
                        @endfor
                    </select>
                </div>
                <div class="mt-3"><label class="form-label" for="review-comment">Your testimonial</label><textarea class="form-control" id="review-comment" name="comment" rows="4" minlength="12" maxlength="1500" required>{{ old('comment') }}</textarea></div>
                <div class="mt-3"><label class="form-label" for="review-profile-image">Profile image <span class="text-muted">(optional; JPG, PNG, or WebP, max 5 MB)</span></label><input class="form-control" id="review-profile-image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp"></div>
                <button class="btn btn-primary rounded-pill mt-3" type="submit">Submit for approval</button>
            </form>
        </div>
    </section>
@endsection
