<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\CustomerReview;
use App\Models\FounderGallery;
use App\Models\Product;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class ContentController extends Controller
{
    public function blog(): View
    {
        $posts = BlogPost::query()
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(9);
        $settings = $this->settings();

        return view('content.blog', compact('posts', 'settings'));
    }

    public function blogPost(string $slug): View
    {
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();
        BlogPost::whereKey($post->id)->update(['views' => DB::raw('COALESCE(views, 0) + 1')]);
        $post->views = (int) $post->views + 1;
        $relatedPosts = BlogPost::query()
            ->where('is_published', true)
            ->whereKeyNot($post->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('content.blog-show', compact('post', 'relatedPosts'));
    }

    public function reviews(): View
    {
        $reviews = CustomerReview::query()->where('is_approved', true)
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate(12);
        $summary = CustomerReview::query()->where('is_approved', true)
            ->selectRaw('COALESCE(AVG(rating), 0) as average_rating, COUNT(*) as total_reviews')
            ->first();

        return view('content.reviews', compact('reviews', 'summary'));
    }

    public function storeCustomerReview(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:12', 'max:1500'],
            'reviewer_name' => [$request->user() ? 'nullable' : 'required', 'string', 'min:2', 'max:255'],
            'reviewer_email' => [$request->user() ? 'nullable' : 'required', 'email', 'max:255'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $user = $request->user();
        $review = $user
            ? CustomerReview::query()->firstOrNew(['user_id' => $user->id])
            : new CustomerReview;
        $profileImage = $request->file('profile_image')
            ? $request->file('profile_image')->store('reviews', 'public')
            : ($review->profile_image ?: $user?->profile_image);
        $previousImage = $review->profile_image;
        if ($request->file('profile_image') && ! $profileImage) {
            throw new RuntimeException('The testimonial image could not be stored.');
        }
        $review->forceFill([
            'user_id' => $user?->id,
            'reviewer_name' => $user?->name ?? trim($data['reviewer_name']),
            'reviewer_email' => $user?->email ?? Str::lower($data['reviewer_email']),
            'profile_image' => $profileImage,
            'rating' => $data['rating'],
            'comment' => trim($data['comment']),
            'is_approved' => false,
        ])->save();
        if ($request->file('profile_image') && $previousImage && $previousImage !== $profileImage && preg_match('#^reviews/#', $previousImage)) {
            Storage::disk('public')->delete($previousImage);
        }
        if ($user && $request->file('profile_image')) {
            $user->forceFill(['profile_image' => $profileImage])->save();
        }

        return redirect()->route('reviews.index')->with('status', 'Your testimonial was submitted for approval.');
    }

    public function storeProductReview(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:12', 'max:1500'],
            'guest_name' => [$request->user() ? 'nullable' : 'required', 'string', 'min:2', 'max:255'],
            'guest_email' => [$request->user() ? 'nullable' : 'required', 'email', 'max:255'],
            'review_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $product = Product::active()->findOrFail((int) $data['product_id']);
        $user = $request->user();
        $review = $user
            ? Review::query()->firstOrNew(['product_id' => $product->id, 'user_id' => $user->id])
            : new Review;
        $reviewImage = $request->file('review_image')
            ? $request->file('review_image')->store('reviews', 'public')
            : $review->review_image;
        $previousImage = $review->review_image;
        if ($request->file('review_image') && ! $reviewImage) {
            throw new RuntimeException('The product review image could not be stored.');
        }
        $review->forceFill([
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'reviewer_name' => $user?->name ?? trim($data['guest_name']),
            'reviewer_email' => $user?->email ?? Str::lower($data['guest_email']),
            'rating' => $data['rating'],
            'comment' => trim($data['comment']),
            'review_image' => $reviewImage,
            'is_approved' => false,
        ])->save();
        if ($request->file('review_image') && $previousImage && $previousImage !== $reviewImage && preg_match('#^reviews/#', $previousImage)) {
            Storage::disk('public')->delete($previousImage);
        }

        return redirect()->route('shop.show', $product->slug)->with('status', 'Your product review was submitted for approval.');
    }

    public function team(): View
    {
        $staff = StaffMember::query()
            ->leftJoin('company_branches', 'company_branches.id', '=', 'staff_members.branch_id')
            ->where('staff_members.is_active', true)
            ->where(function ($query): void {
                $query->whereNull('staff_members.branch_id')->orWhere('company_branches.is_active', true);
            })
            ->orderBy('staff_members.sort_order')
            ->orderBy('staff_members.id')
            ->select('staff_members.*', 'company_branches.branch_name')
            ->get();
        $settings = $this->settings();

        return view('content.team', compact('staff', 'settings'));
    }

    public function founder(): View
    {
        $settings = $this->settings();
        $gallery = FounderGallery::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('content.founder', compact('settings', 'gallery'));
    }

    private function settings()
    {
        return SiteSetting::query()->pluck('setting_value', 'setting_key');
    }
}
