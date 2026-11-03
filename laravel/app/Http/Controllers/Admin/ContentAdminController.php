<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PlainTextNotification;
use App\Models\Appointment;
use App\Models\BlogPost;
use App\Models\Contact;
use App\Models\CustomerReview;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ContentAdminController extends Controller
{
    public function contacts(): View
    {
        $contacts = Contact::query()->latest('created_at')->paginate(20);

        return view('admin.content.contacts', compact('contacts'));
    }

    public function updateContact(Request $request, Contact $contact): RedirectResponse
    {
        $contact->update([
            'is_read' => $request->boolean('is_read'),
            'replied' => $request->boolean('replied'),
            'replied_at' => $request->boolean('replied') ? now() : null,
        ]);

        return back()->with('status', 'Contact message updated.');
    }

    public function appointments(): View
    {
        $appointments = Appointment::query()->latest('appointment_date')->paginate(20);

        return view('admin.content.appointments', compact('appointments'));
    }

    public function updateAppointment(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'confirmed', 'completed', 'cancelled', 'no_show'])],
        ]);
        $previousStatus = $appointment->status;
        $appointment->update($data);
        if ($previousStatus !== $appointment->status && in_array($appointment->status, ['confirmed', 'cancelled'], true)) {
            try {
                Mail::to($appointment->email)->send(new PlainTextNotification(
                    'Appointment status updated',
                    "Hello {$appointment->name},\n\nYour appointment request for ".
                    $appointment->appointment_date->format('F j, Y').' at '.
                    Carbon::parse($appointment->appointment_time)->format('g:i A').
                    ' is now '.$appointment->status.".\n\nBealet Optical Center"
                ));
            } catch (TransportExceptionInterface $exception) {
                Log::warning('Appointment status email could not be delivered.', [
                    'appointment_id' => $appointment->id,
                    'recipient' => $appointment->email,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return back()->with('status', 'Appointment status updated.');
    }

    public function reviews(): View
    {
        $customerReviews = CustomerReview::query()->latest('updated_at')->paginate(15, ['*'], 'customer_page');
        $productReviews = Review::query()->with('product')->latest('updated_at')->paginate(15, ['*'], 'product_page');

        return view('admin.content.reviews', compact('customerReviews', 'productReviews'));
    }

    public function updateCustomerReview(Request $request, CustomerReview $review): RedirectResponse
    {
        $data = $request->validate([
            'reviewer_name' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:12', 'max:1500'],
            'is_approved' => ['sometimes', 'boolean'],
        ]);
        $review->update([
            'reviewer_name' => trim($data['reviewer_name']),
            'rating' => $data['rating'],
            'comment' => trim($data['comment']),
            'is_approved' => $request->boolean('is_approved'),
        ]);

        return back()->with('status', 'Customer testimonial updated.');
    }

    public function updateProductReview(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'is_approved' => ['required', 'boolean'],
        ]);
        $review->update(['is_approved' => $data['is_approved']]);

        return back()->with('status', 'Product review moderation updated.');
    }

    public function blog(): View
    {
        $posts = BlogPost::query()->orderByDesc('updated_at')->paginate(20);

        return view('admin.content.blog-index', compact('posts'));
    }

    public function createBlogPost(): View
    {
        return view('admin.content.blog-form', ['post' => new BlogPost]);
    }

    public function storeBlogPost(Request $request): RedirectResponse
    {
        $data = $this->validatedBlogPost($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $this->ensureUniqueSlug($data['slug']);
        if ($request->file('featured_image_upload')) {
            $data['featured_image'] = $request->file('featured_image_upload')->store('blog', 'public');
            if (! $data['featured_image']) {
                throw new RuntimeException('The blog image could not be stored.');
            }
        }
        $published = $request->boolean('is_published');
        BlogPost::create([
            ...$data,
            'author_id' => $request->user()->id,
            'is_published' => $published,
            'published_at' => $published ? now() : null,
        ]);

        return redirect()->route('admin.content.blog')->with('status', 'Blog post saved.');
    }

    public function editBlogPost(BlogPost $post): View
    {
        return view('admin.content.blog-form', compact('post'));
    }

    public function updateBlogPost(Request $request, BlogPost $post): RedirectResponse
    {
        $data = $this->validatedBlogPost($request, $post);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $this->ensureUniqueSlug($data['slug'], $post);
        $previousImage = $post->featured_image;
        if ($request->file('featured_image_upload')) {
            $storedImage = $request->file('featured_image_upload')->store('blog', 'public');
            if (! $storedImage) {
                throw new RuntimeException('The blog image could not be stored.');
            }
            $data['featured_image'] = $storedImage;
        } elseif (! $data['featured_image']) {
            $data['featured_image'] = $post->featured_image;
        }
        $published = $request->boolean('is_published');
        $post->update([
            ...$data,
            'is_published' => $published,
            'published_at' => $published ? ($post->published_at ?? now()) : null,
        ]);
        if ($previousImage && $previousImage !== $post->featured_image && preg_match('#^blog/#', $previousImage)) {
            Storage::disk('public')->delete($previousImage);
        }

        return redirect()->route('admin.content.blog')->with('status', 'Blog post updated.');
    }

    public function destroyBlogPost(BlogPost $post): RedirectResponse
    {
        if ($post->featured_image && preg_match('#^blog/#', $post->featured_image)) {
            Storage::disk('public')->delete($post->featured_image);
        }
        $post->delete();

        return back()->with('status', 'Blog post deleted.');
    }

    private function validatedBlogPost(Request $request, ?BlogPost $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blog_posts', 'slug')->ignore($post?->id)],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string', 'max:50000'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'featured_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        unset($data['featured_image_upload']);
        $data += [
            'slug' => '',
            'excerpt' => null,
            'featured_image' => null,
        ];

        return $data;
    }

    private function ensureUniqueSlug(string $slug, ?BlogPost $post = null): void
    {
        $query = BlogPost::query()->where('slug', $slug);
        if ($post) {
            $query->whereKeyNot($post->id);
        }

        if ($slug === '' || $query->exists()) {
            throw ValidationException::withMessages([
                'slug' => $slug === '' ? 'Please provide a title that can form a URL slug.' : 'That URL slug is already in use.',
            ]);
        }
    }
}
