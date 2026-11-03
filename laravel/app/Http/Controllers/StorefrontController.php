<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\CustomerReview;
use App\Models\Product;
use App\Models\Review;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function home()
    {
        $featuredProducts = Product::active()->with('images')
            ->where(function ($query): void {
                $query->where('is_featured', true)->orWhere('featured', true);
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('featured')
            ->latest('id')
            ->limit(6)
            ->get();

        if ($featuredProducts->count() < 6) {
            $featuredProducts = $featuredProducts->concat(
                Product::active()
                    ->with('images')
                    ->whereNotIn('id', $featuredProducts->modelKeys())
                    ->latest('id')
                    ->limit(6 - $featuredProducts->count())
                    ->get()
            )->unique('id')->values();
        }

        $customerReviews = CustomerReview::query()
            ->with('user')
            ->where('is_approved', true)
            ->latest('updated_at')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (CustomerReview $review): array => [
                'type' => 'customer',
                'name' => $review->reviewer_name ?: $review->user?->name ?: 'Customer',
                'title' => 'Customer Testimonial',
                'rating' => $review->rating,
                'comment' => $review->comment,
                'image' => $review->profile_image ?: $review->user?->profile_image,
                'testimonial_image' => null,
                'date' => $review->updated_at ?: $review->created_at,
                'product_name' => null,
            ]);

        $productReviews = Review::query()
            ->with(['user', 'product'])
            ->where('is_approved', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->latest('updated_at')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (Review $review): array => [
                'type' => 'product',
                'name' => $review->reviewer_name ?: $review->user?->name ?: 'Customer',
                'title' => $review->product?->name ? $review->product->name.' testimonial' : 'Product Testimonial',
                'rating' => $review->rating,
                'comment' => $review->comment,
                'image' => $review->user?->profile_image,
                'testimonial_image' => $review->review_image,
                'date' => $review->updated_at ?: $review->created_at,
                'product_name' => $review->product?->name,
            ]);

        $homepageReviews = $customerReviews->concat($productReviews)
            ->sortByDesc(fn (array $review) => $review['date']?->getTimestamp() ?? 0)
            ->take(3)
            ->values();
        $customerSummary = CustomerReview::query()
            ->where('is_approved', true)
            ->selectRaw('COALESCE(AVG(rating), 0) as average_rating, COUNT(*) as total_reviews')
            ->first();
        $productSummary = Review::query()
            ->where('is_approved', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->selectRaw('COALESCE(AVG(rating), 0) as average_rating, COUNT(*) as total_reviews')
            ->first();
        $customerReviewCount = (int) $customerSummary->total_reviews;
        $productReviewCount = (int) $productSummary->total_reviews;
        $totalPublishedReviews = $customerReviewCount + $productReviewCount;
        $combinedAverageRating = $totalPublishedReviews > 0
            ? (((float) $customerSummary->average_rating * $customerReviewCount)
                + ((float) $productSummary->average_rating * $productReviewCount)) / $totalPublishedReviews
            : 0;
        $blogs = BlogPost::query()
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();
        $settings = SiteSetting::query()->pluck('setting_value', 'setting_key');

        return view('storefront.home', compact(
            'featuredProducts',
            'homepageReviews',
            'customerReviewCount',
            'productReviewCount',
            'totalPublishedReviews',
            'combinedAverageRating',
            'blogs',
            'settings',
        ));
    }

    public function index(Request $request)
    {
        if ($request->filled('view_product')) {
            $product = Product::active()->findOrFail((int) $request->query('view_product'));

            return redirect()->route('shop.show', $product->slug, 301);
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:50'],
            'frame_target' => ['nullable', 'string', 'max:50'],
            'brand' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'try_on' => ['nullable', 'in:ready'],
            'sort' => ['nullable', 'in:newest,price_low,price_high,popular,featured'],
        ]);

        $query = Product::active()->with('images');

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('brand', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        foreach (['category', 'frame_target', 'brand'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (($filters['try_on'] ?? null) === 'ready') {
            $query->where(function ($builder): void {
                foreach (['ar_model_2d', 'ar_model_2d_left', 'ar_model_2d_right', 'ar_model_3d'] as $column) {
                    $builder->orWhere(fn ($asset) => $asset->whereNotNull($column)->where($column, '<>', ''));
                }
            });
        }

        match ($filters['sort'] ?? 'newest') {
            'price_low' => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            'popular' => $query->orderByDesc('id'),
            'featured' => $query->orderByDesc('is_featured')->orderByDesc('featured')->orderByDesc('id'),
            default => $query->orderByDesc('created_at'),
        };

        $products = $query->paginate(12)->withQueryString();
        $categories = Product::active()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
        $frameTargets = Product::active()->whereNotNull('frame_target')->where('frame_target', '<>', '')
            ->distinct()->orderBy('frame_target')->pluck('frame_target');
        $brands = Product::active()->whereNotNull('brand')->where('brand', '<>', '')
            ->distinct()->orderBy('brand')->pluck('brand');

        return view('storefront.shop.index', compact('products', 'categories', 'frameTargets', 'brands', 'filters'));
    }

    public function show(string $slug)
    {
        $product = Product::active()->with('images')->where('slug', $slug)->firstOrFail();
        $relatedProducts = Product::active()
            ->where('category', $product->category)
            ->whereKeyNot($product->getKey())
            ->latest('id')
            ->limit(4)
            ->get();
        $reviews = Review::query()->with('user')
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->latest('created_at')
            ->paginate(8);
        $reviewSummary = Review::query()->where('product_id', $product->id)
            ->where('is_approved', true)
            ->selectRaw('COALESCE(AVG(rating), 0) as average_rating, COUNT(*) as total_reviews')
            ->first();

        return view('storefront.shop.show', compact('product', 'relatedProducts', 'reviews', 'reviewSummary'));
    }
}
