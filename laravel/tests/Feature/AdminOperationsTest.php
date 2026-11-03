<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Contact;
use App\Models\CustomerReview;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\StaffMember;
use App\Models\TrackingHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sub_admin_is_limited_to_operational_admin_routes(): void
    {
        $admin = $this->admin('sub_admin');

        $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.site.settings'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.site.staff'))->assertForbidden();
    }

    public function test_admin_overview_shows_live_business_metrics_and_work_queues(): void
    {
        $admin = $this->admin('sub_admin');
        $product = $this->product();
        $this->order()->update([
            'guest_name' => 'Ama Customer',
            'total_amount' => 250,
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);
        Appointment::create([
            'name' => 'Kojo Mensah',
            'email' => 'kojo@example.com',
            'phone' => '+233201234567',
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:30:00',
            'appointment_type' => 'eye_exam',
            'status' => 'confirmed',
        ]);
        Contact::create([
            'name' => 'Customer Message',
            'email' => 'message@example.com',
            'subject' => 'Opening hours',
            'message' => 'What time do you close?',
            'is_read' => false,
        ]);
        CustomerReview::create([
            'reviewer_name' => 'Pending Customer',
            'rating' => 5,
            'comment' => 'A pending customer testimonial for moderation.',
            'is_approved' => false,
        ]);
        Review::create([
            'product_id' => $product->id,
            'reviewer_name' => 'Pending Product Reviewer',
            'rating' => 4,
            'comment' => 'A pending product review for moderation.',
            'is_approved' => false,
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Orders to process')
            ->assertSee('Sales this month')
            ->assertSee('GH₵ 250.00')
            ->assertSee('Appointments today')
            ->assertSee('Low stock')
            ->assertSee('unread message')
            ->assertSee('2 reviews to moderate')
            ->assertSee('Ama Customer')
            ->assertSee('Kojo Mensah')
            ->assertSee('legacy-assets/images/logo/logo.png')
            ->assertSee('bealet-admin-theme')
            ->assertSee('Switch to dark mode')
            ->assertSee('View storefront')
            ->assertDontSee('Manage products');
    }

    public function test_super_admin_can_create_update_and_hide_products_with_safe_image_uploads(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Modern Frame',
            'category' => 'eyeglasses',
            'price' => '240.00',
            'stock' => 12,
            'low_stock_threshold' => 3,
            'is_active' => '1',
            'is_featured' => '1',
            'product_image' => UploadedFile::fake()->image('frame.jpg'),
        ])->assertRedirect();

        $product = Product::firstOrFail();
        $this->assertSame('modern-frame', $product->slug);
        $this->assertTrue($product->is_featured);
        $this->assertStringStartsWith('products/', $product->main_image);
        Storage::disk('public')->assertExists($product->main_image);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => 'Modern Frame',
            'category' => 'eyeglasses',
            'price' => '240.00',
            'stock' => 8,
            'low_stock_threshold' => 3,
            'is_active' => '1',
            'is_featured' => '1',
        ])->assertRedirect();
        $this->assertSame(8, (int) $product->fresh()->stock);

        $this->actingAs($admin)->patch(route('admin.products.deactivate', $product))->assertRedirect();
        $this->assertFalse($product->fresh()->is_active);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Modern Frame']);
    }

    public function test_invalid_glb_payload_is_rejected_without_creating_a_product(): void
    {
        $this->actingAs($this->admin())->from(route('admin.products.create'))
            ->post(route('admin.products.store'), [
                'name' => 'Invalid Model',
                'category' => 'eyeglasses',
                'price' => '120.00',
                'stock' => 1,
                'low_stock_threshold' => 1,
                'is_active' => '1',
                'ar_model_3d' => UploadedFile::fake()->createWithContent('invalid.glb', 'not a GLB file'),
            ])
            ->assertSessionHasErrors('ar_model_3d');

        $this->assertDatabaseMissing('products', ['name' => 'Invalid Model']);
    }

    public function test_inventory_changes_are_validated_and_committed(): void
    {
        $product = $this->product();
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.inventory.update', $product), [
            'stock' => 18,
            'low_stock_threshold' => 4,
        ])->assertRedirect();
        $this->assertSame(18, (int) $product->fresh()->stock);

        $this->actingAs($admin)->patch(route('admin.inventory.update', $product), [
            'stock' => -2,
            'low_stock_threshold' => 4,
        ])->assertSessionHasErrors('stock');
    }

    public function test_order_fulfillment_requires_verified_payment_and_moves_forward_only(): void
    {
        $order = $this->order();
        $admin = $this->admin();
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), [
            'status' => 'processing',
        ])->assertStatus(422);

        $order->update(['payment_status' => 'paid', 'status' => 'processing']);
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), [
            'status' => 'shipped',
            'tracking_number' => 'TRACK-123',
            'courier_name' => 'Local Courier',
        ])->assertRedirect();
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame(1, TrackingHistory::where('order_id', $order->id)->count());

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), [
            'status' => 'processing',
        ])->assertStatus(422);
    }

    public function test_super_admin_can_update_public_site_settings_staff_and_gallery(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.site.settings.update'), [
            'company_name' => 'Bealet Optical',
            'homepage_hero_title' => 'Frames for everyone',
        ])->assertRedirect();
        $this->assertSame('Frames for everyone', SiteSetting::where('setting_key', 'homepage_hero_title')->value('setting_value'));
        $this->get(route('home'))->assertOk()->assertSee('Frames for everyone');

        $this->actingAs($admin)->post(route('admin.site.staff.store'), [
            'name' => 'Ama Mensah',
            'designation' => 'Optometrist',
            'sort_order' => 1,
            'is_active' => '1',
            'thumbnail_upload' => UploadedFile::fake()->image('staff.jpg'),
        ])->assertRedirect();
        $staff = StaffMember::firstOrFail();
        Storage::disk('public')->assertExists($staff->thumbnail);
        $this->assertDatabaseHas('staff_members', ['name' => 'Ama Mensah', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.site.gallery.store'), [
            'item_title' => 'Founder portrait',
            'item_type' => 'portrait',
            'sort_order' => 1,
            'is_active' => '1',
            'image_upload' => UploadedFile::fake()->image('founder.jpg'),
        ])->assertRedirect();
        $this->assertDatabaseHas('founder_gallery', ['item_title' => 'Founder portrait', 'is_active' => true]);

        $this->get(route('admin.products.index'))->assertOk();
        $this->get(route('admin.products.create'))->assertOk();
        $this->get(route('admin.inventory.index'))->assertOk();
        $this->get(route('admin.orders.index'))->assertOk();
        $this->get(route('admin.customers.index'))->assertOk();
        $this->get(route('admin.site.settings'))->assertOk();
        $this->get(route('admin.site.staff'))->assertOk();
        $this->get(route('admin.site.gallery'))->assertOk();
        $this->get(route('team'))->assertOk();
        $this->get(route('founder'))->assertOk();
    }

    public function test_try_on_page_exposes_active_assets_and_legacy_url_redirects(): void
    {
        SiteSetting::create(['setting_key' => 'whatsapp_phone', 'setting_value' => '0241234567']);
        Product::create([
            'name' => 'AR Frame',
            'slug' => 'ar-frame',
            'category' => 'eyeglasses',
            'price' => 100,
            'stock' => 2,
            'is_active' => true,
            'ar_model_2d' => 'assets/uploads/ar-models/frame.png',
        ]);

        $this->get(route('tryon.index'))
            ->assertOk()
            ->assertSee('Virtual fitting room')
            ->assertSee('ar-frame')
            ->assertSee('tryon-zoom')
            ->assertSee('Once your face is detected, drag the preview to reposition the frame.')
            ->assertSee('Pinch or scroll to zoom.')
            ->assertDontSee('GH₵ 100.00')
            ->assertSee('Chat with us on WhatsApp');
        $this->get('/ar-tryon.php')->assertRedirect('/ar-tryon');
    }

    private function admin(string $role = 'super_admin'): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => 'admin-'.uniqid().'@example.com',
            'phone' => '+233241234567',
            'password_hash' => Hash::make('StrongPass1!'),
            'is_admin' => true,
            'admin_role' => $role,
            'is_active' => true,
        ]);
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Test Frame',
            'slug' => 'test-frame',
            'category' => 'eyeglasses',
            'price' => 100,
            'stock' => 5,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);
    }

    private function order(): Order
    {
        return Order::create([
            'tracking_code' => 'TEST-'.strtoupper(substr(uniqid(), -8)),
            'subtotal' => 100,
            'total_amount' => 100,
            'shipping_address' => '1 Main Street',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }
}
