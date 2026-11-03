<?php

namespace Tests\Feature;

use App\Mail\PlainTextNotification;
use App\Models\Appointment;
use App\Models\BlogPost;
use App\Models\CompanyBranch;
use App\Models\Contact;
use App\Models\CustomerReview;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppointmentsAndContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointment_request_is_saved_and_customer_receives_confirmation(): void
    {
        Mail::fake();
        $date = Carbon::today()->addDays(2);
        while ($date->isSunday()) {
            $date->addDay();
        }

        $this->post(route('appointments.store'), [
            'name' => 'Ama Mensah',
            'email' => 'AMA@example.com',
            'phone' => '0241234567',
            'appointment_date' => $date->toDateString(),
            'appointment_time' => '10:00',
            'appointment_type' => 'eye_exam',
            'notes' => 'Vision check',
        ])->assertRedirect(route('appointments.create'))
            ->assertSessionHas('status');

        $appointment = Appointment::firstOrFail();
        $this->assertDatabaseHas('appointment_slot_locks', [
            'appointment_date' => $date->toDateString(),
            'appointment_time' => '10:00:00',
        ]);
        $this->assertSame('ama@example.com', $appointment->email);
        $this->assertSame('pending', $appointment->status);
        $this->assertSame('10:00', substr((string) $appointment->appointment_time, 0, 5));
        Mail::assertSentCount(2);
        Mail::assertSent(PlainTextNotification::class, fn (PlainTextNotification $mail): bool => $mail->hasTo('ama@example.com'));
    }

    public function test_appointment_rejects_sundays_invalid_slots_and_duplicate_requests(): void
    {
        $sunday = Carbon::today()->next(Carbon::SUNDAY);
        $validDate = Carbon::today()->addDays(2);
        while ($validDate->isSunday()) {
            $validDate->addDay();
        }
        Appointment::create([
            'name' => 'Existing Customer',
            'email' => 'existing@example.com',
            'phone' => '+233241234567',
            'appointment_date' => $validDate->toDateString(),
            'appointment_time' => '10:00',
            'appointment_type' => 'eye_exam',
            'status' => 'pending',
        ]);

        $payload = [
            'name' => 'Ama Mensah',
            'email' => 'ama@example.com',
            'phone' => '0241234567',
            'appointment_date' => $sunday->toDateString(),
            'appointment_time' => '10:00',
            'appointment_type' => 'eye_exam',
        ];
        $this->from(route('appointments.create'))->post(route('appointments.store'), $payload)
            ->assertSessionHasErrors('appointment_date');

        $payload['appointment_date'] = $validDate->toDateString();
        $this->from(route('appointments.create'))->post(route('appointments.store'), $payload)
            ->assertSessionHasErrors('appointment_time');

        $payload['appointment_time'] = '17:00';
        $this->from(route('appointments.create'))->post(route('appointments.store'), $payload)
            ->assertSessionHasErrors('appointment_time');
    }

    public function test_customer_can_only_cancel_their_own_pending_appointment(): void
    {
        $user = $this->customer('ama@example.com');
        $other = $this->customer('kojo@example.com');
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'appointment_date' => Carbon::today()->addDays(3)->toDateString(),
            'appointment_time' => '11:00',
            'appointment_type' => 'consultation',
            'status' => 'pending',
        ]);

        $this->actingAs($other)->patch(route('appointments.cancel', $appointment))->assertNotFound();
        $this->actingAs($user)->patch(route('appointments.cancel', $appointment))
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame('cancelled', $appointment->fresh()->status);
    }

    public function test_admin_can_update_appointment_status_but_customer_cannot(): void
    {
        Mail::fake();
        $customer = $this->customer('ama@example.com');
        $appointment = Appointment::create([
            'user_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'appointment_date' => Carbon::today()->addDays(3)->toDateString(),
            'appointment_time' => '11:00',
            'appointment_type' => 'consultation',
            'status' => 'pending',
        ]);

        $this->actingAs($customer)->patch(route('admin.content.appointments.update', $appointment), [
            'status' => 'confirmed',
        ])->assertForbidden();

        $admin = $this->customer('admin@example.com', admin: true);
        $this->actingAs($admin)->patch(route('admin.content.appointments.update', $appointment), [
            'status' => 'confirmed',
        ])->assertRedirect();
        $this->assertSame('confirmed', $appointment->fresh()->status);
        Mail::assertSent(PlainTextNotification::class, fn (PlainTextNotification $mail): bool => $mail->hasTo('ama@example.com'));
    }

    public function test_admin_can_mark_contact_messages_read_and_replied(): void
    {
        $contact = Contact::create([
            'name' => 'Ama Mensah',
            'email' => 'ama@example.com',
            'message' => 'Please contact me about an appointment.',
            'is_read' => false,
            'replied' => false,
        ]);
        $customer = $this->customer('ama@example.com');
        $this->actingAs($customer)->patch(route('admin.content.contacts.update', $contact), [
            'is_read' => '1',
            'replied' => '1',
        ])->assertForbidden();

        $admin = $this->customer('admin@example.com', admin: true);
        $this->actingAs($admin)->patch(route('admin.content.contacts.update', $contact), [
            'is_read' => '1',
            'replied' => '1',
        ])->assertRedirect();
        $this->assertTrue($contact->fresh()->is_read);
        $this->assertTrue($contact->fresh()->replied);
        $this->assertNotNull($contact->fresh()->replied_at);
    }

    public function test_contact_message_is_saved_and_acknowledgement_is_sent(): void
    {
        Mail::fake();
        config(['services.contact_notification_email' => 'team@example.com']);

        $this->post(route('contact.store'), [
            'name' => 'Ama Mensah',
            'email' => 'ama@example.com',
            'message' => 'I would like to know more about your optical services.',
        ])->assertRedirect(route('contact.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('contacts', [
            'name' => 'Ama Mensah',
            'email' => 'ama@example.com',
            'is_read' => false,
        ]);
        Mail::assertSentCount(2);
    }

    public function test_newsletter_subscription_is_idempotent_and_can_be_reactivated(): void
    {
        $response = $this->post(route('newsletter.store'), ['email' => 'Ama@example.com']);
        $response->assertRedirect();
        $subscriber = NewsletterSubscriber::firstOrFail();
        $token = $subscriber->unsubscribe_token;

        $this->postJson(route('newsletter.api'), ['email' => 'ama@example.com'])
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertSame(1, NewsletterSubscriber::count());
        $this->postJson(route('newsletter.api'), ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->get(route('newsletter.unsubscribe', $token))->assertOk();
        $this->assertTrue($subscriber->fresh()->is_active);
        $this->post(route('newsletter.unsubscribe.confirm', $token))->assertRedirect(route('home'));
        $this->assertFalse($subscriber->fresh()->is_active);

        $this->post(route('newsletter.store'), ['email' => 'ama@example.com'])->assertRedirect();
        $this->assertTrue($subscriber->fresh()->is_active);
        $this->assertNull($subscriber->fresh()->unsubscribed_at);
    }

    public function test_public_blog_only_shows_published_posts_and_legacy_links_redirect(): void
    {
        $published = BlogPost::create([
            'title' => 'Choosing eyewear',
            'slug' => 'choosing-eyewear',
            'excerpt' => 'A practical guide.',
            'content' => 'Look for a comfortable frame.',
            'is_published' => true,
            'published_at' => now(),
        ]);
        BlogPost::create([
            'title' => 'Draft guide',
            'slug' => 'draft-guide',
            'content' => 'Not ready to publish.',
            'is_published' => false,
        ]);

        $this->get(route('blog.index'))->assertOk()
            ->assertSee('Choosing eyewear')
            ->assertDontSee('Draft guide');
        $this->get(route('blog.show', 'choosing-eyewear'))->assertOk()->assertSee('Look for a comfortable frame.');
        $this->assertSame(1, (int) $published->fresh()->views);
        $this->get('/blog-post.php?slug=choosing-eyewear')->assertRedirect(route('blog.show', 'choosing-eyewear'));
        $this->get(route('blog.show', 'draft-guide'))->assertNotFound();
    }

    public function test_admin_can_create_update_publish_and_delete_blog_posts(): void
    {
        $admin = $this->customer('admin@example.com', admin: true);
        $this->actingAs($admin)->post(route('admin.content.blog.store'), [
            'title' => 'Optical care guide',
            'slug' => '',
            'excerpt' => 'Tips for healthy eyes.',
            'content' => 'Visit your optician regularly.',
            'is_published' => '1',
        ])->assertRedirect(route('admin.content.blog'));

        $post = BlogPost::firstOrFail();
        $this->assertSame('optical-care-guide', $post->slug);
        $this->assertTrue($post->is_published);

        $this->actingAs($admin)->put(route('admin.content.blog.update', $post), [
            'title' => 'Optical care guide updated',
            'slug' => 'optical-care-guide',
            'content' => 'Updated and saved.',
        ])->assertRedirect(route('admin.content.blog'));
        $this->assertFalse($post->fresh()->is_published);

        $this->actingAs($admin)->delete(route('admin.content.blog.destroy', $post))->assertRedirect();
        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
    }

    public function test_blog_featured_image_upload_is_stored_on_the_public_disk(): void
    {
        Storage::fake('public');
        $admin = $this->customer('admin@example.com', admin: true);

        $this->actingAs($admin)->post(route('admin.content.blog.store'), [
            'title' => 'Image guide',
            'content' => 'A guide with a featured image.',
            'featured_image_upload' => UploadedFile::fake()->image('guide.webp'),
        ])->assertRedirect(route('admin.content.blog'));

        $post = BlogPost::firstOrFail();
        $this->assertStringStartsWith('blog/', $post->featured_image);
        Storage::disk('public')->assertExists($post->featured_image);
    }

    public function test_customer_testimonials_require_moderation_and_only_approved_reviews_are_public(): void
    {
        $user = $this->customer('ama@example.com');
        $this->actingAs($user)->post(route('reviews.store'), [
            'rating' => 5,
            'comment' => 'Excellent optical service and kind staff.',
        ])->assertRedirect(route('reviews.index'));

        $review = CustomerReview::firstOrFail();
        $this->assertFalse($review->is_approved);
        $this->get(route('reviews.index'))->assertOk()->assertDontSee('Excellent optical service');

        $this->actingAs($user)->patch(route('admin.content.reviews.customer.update', $review), [
            'reviewer_name' => 'Ama Mensah',
            'rating' => 5,
            'comment' => 'Excellent optical service and kind staff.',
            'is_approved' => 1,
        ])->assertForbidden();

        $admin = $this->customer('admin@example.com', admin: true);
        $this->actingAs($admin)->patch(route('admin.content.reviews.customer.update', $review), [
            'reviewer_name' => 'Ama Mensah',
            'rating' => 5,
            'comment' => 'Excellent optical service and kind staff.',
            'is_approved' => 1,
        ])->assertRedirect();
        $this->get(route('reviews.index'))->assertSee('Excellent optical service');
    }

    public function test_customer_can_submit_a_product_review_for_moderation(): void
    {
        $product = Product::create([
            'name' => 'Reviewable Frame',
            'slug' => 'reviewable-frame',
            'category' => 'frames',
            'price' => 500,
            'stock' => 2,
            'is_active' => true,
        ]);

        $this->post(route('product-reviews.store'), [
            'product_id' => $product->id,
            'rating' => 4,
            'guest_name' => 'Kojo Mensah',
            'guest_email' => 'kojo@example.com',
            'comment' => 'Comfortable frame and helpful service.',
        ])->assertRedirect(route('shop.show', $product->slug));

        $review = Review::firstOrFail();
        $this->assertFalse($review->is_approved);
        $this->get(route('shop.show', $product->slug))->assertDontSee('Comfortable frame and helpful service.');

        $admin = $this->customer('admin@example.com', admin: true);
        $this->actingAs($admin)->patch(route('admin.content.reviews.product.update', $review), [
            'is_approved' => 1,
        ])->assertRedirect();
        $this->get(route('shop.show', $product->slug))->assertSee('Comfortable frame and helpful service.');
    }

    public function test_product_review_image_upload_is_stored_on_the_public_disk(): void
    {
        Storage::fake('public');
        $product = Product::create([
            'name' => 'Photo Review Frame',
            'slug' => 'photo-review-frame',
            'category' => 'frames',
            'price' => 500,
            'stock' => 1,
            'is_active' => true,
        ]);
        $this->post(route('product-reviews.store'), [
            'product_id' => $product->id,
            'rating' => 5,
            'guest_name' => 'Kojo Mensah',
            'guest_email' => 'kojo@example.com',
            'comment' => 'The frame feels great and looks wonderful.',
            'review_image' => UploadedFile::fake()->image('frame.png'),
        ])->assertRedirect(route('shop.show', $product->slug));

        $review = Review::firstOrFail();
        $this->assertStringStartsWith('reviews/', $review->review_image);
        Storage::disk('public')->assertExists($review->review_image);
    }

    public function test_team_founder_and_contact_pages_use_published_content(): void
    {
        CompanyBranch::create([
            'branch_name' => 'Accra Branch',
            'address' => 'Oxford Street',
            'is_active' => true,
        ]);
        StaffMember::create([
            'name' => 'Dr. Mensah',
            'designation' => 'Optometrist',
            'branch_id' => CompanyBranch::first()->id,
            'is_active' => true,
        ]);
        SiteSetting::create(['setting_key' => 'founder_name', 'setting_value' => 'Ama Bealet']);
        SiteSetting::create(['setting_key' => 'founder_story', 'setting_value' => 'A story of clear vision.']);

        $this->get(route('team'))->assertOk()->assertSee('Dr. Mensah')->assertSee('Accra Branch');
        $this->get(route('founder'))->assertOk()->assertSee('Ama Bealet')->assertSee('A story of clear vision.');
        $this->get(route('contact.index'))->assertOk()->assertSee('Accra Branch');
    }

    private function customer(string $email, bool $admin = false): User
    {
        return User::create([
            'name' => $admin ? 'Bealet Admin' : 'Ama Mensah',
            'email' => $email,
            'phone' => '+233241234567',
            'password_hash' => Hash::make('StrongPass1!'),
            'is_admin' => $admin,
            'admin_role' => $admin ? 'admin' : 'customer',
            'is_active' => true,
        ]);
    }
}
