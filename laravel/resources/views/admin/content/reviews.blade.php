@extends('admin.layout')

@section('title', 'Moderate reviews | ' . config('app.name'))

@section('admin-content')
    <section class="container-lg account-page">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><span class="storefront-eyebrow storefront-eyebrow-dark">Administration</span><h1 class="h2 fw-bold mb-0">Review moderation</h1></div>
            <a class="btn btn-outline-primary rounded-pill" href="{{ route('admin.dashboard') }}">Admin home</a>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        <h2 class="h4 fw-bold mt-4">Customer testimonials</h2>
        @forelse ($customerReviews as $review)
            <form class="account-card mb-3" method="POST" action="{{ route('admin.content.reviews.customer.update', $review) }}">
                @csrf
                @method('PATCH')
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label" for="customer-reviewer-{{ $review->id }}">Name</label><input class="form-control" id="customer-reviewer-{{ $review->id }}" name="reviewer_name" value="{{ $review->reviewer_name }}" required></div>
                    <div class="col-md-2"><label class="form-label" for="customer-rating-{{ $review->id }}">Rating</label><select class="form-select" id="customer-rating-{{ $review->id }}" name="rating">@for ($rating = 1; $rating <= 5; $rating++)<option value="{{ $rating }}" @selected($review->rating === $rating)>{{ $rating }}</option>@endfor</select></div>
                    <div class="col-12"><label class="form-label" for="customer-comment-{{ $review->id }}">Testimonial</label><textarea class="form-control" id="customer-comment-{{ $review->id }}" name="comment" rows="3" required>{{ $review->comment }}</textarea></div>
                    <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="is_approved" value="1" @checked($review->is_approved)> Approved for publication</label>
                        <button class="btn btn-sm btn-primary rounded-pill" type="submit">Save review</button>
                        <span class="small text-muted">{{ $review->reviewer_email }}</span>
                    </div>
                </div>
            </form>
        @empty
            <p class="text-muted">No customer testimonials.</p>
        @endforelse
        {{ $customerReviews->links() }}

        <h2 class="h4 fw-bold mt-5">Product reviews</h2>
        <div class="account-card table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Product / reviewer</th><th>Rating and review</th><th>Moderation</th></tr></thead>
                <tbody>
                    @forelse ($productReviews as $review)
                        <tr>
                            <td>{{ $review->product?->name ?? 'Removed product' }}<span class="d-block small text-muted">{{ $review->reviewer_name ?: $review->reviewer_email }}</span></td>
                            <td>{{ $review->rating }}/5<span class="d-block small text-muted">{{ $review->comment }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('admin.content.reviews.product.update', $review) }}" class="d-flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select class="form-select form-select-sm" name="is_approved" aria-label="Product review approval">
                                        <option value="1" @selected($review->is_approved)>Approved</option>
                                        <option value="0" @selected(!$review->is_approved)>Pending</option>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">No product reviews.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $productReviews->links() }}
    </section>
@endsection
