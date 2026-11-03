<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountsAndCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paystack.secret_key' => 'test-secret',
            'services.paystack.currency' => 'GHS',
        ]);
    }

    public function test_customer_can_register_and_sign_in_with_legacy_password_column(): void
    {
        $this->post('/register', [
            'name' => 'Ama Mensah',
            'email' => 'ama@example.com',
            'phone' => '0241234567',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
        ])->assertRedirect(route('account.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'ama@example.com',
            'is_admin' => false,
            'admin_role' => 'customer',
        ]);
        $this->assertNotSame('StrongPass1!', User::where('email', 'ama@example.com')->value('password_hash'));

        $this->post('/logout')->assertRedirect(route('home'));
        $this->post('/login', [
            'email' => 'ama@example.com',
            'password' => 'StrongPass1!',
        ])->assertRedirect(route('account.index'));
        $this->assertAuthenticated();
    }

    public function test_customer_cannot_open_admin_dashboard(): void
    {
        $customer = User::create([
            'name' => 'Ama Mensah',
            'email' => 'ama@example.com',
            'phone' => '+233241234567',
            'password_hash' => Hash::make('StrongPass1!'),
            'is_admin' => false,
            'is_active' => true,
        ]);

        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_account_shows_only_the_signed_in_customers_appointments(): void
    {
        $customer = $this->customer();
        $otherCustomer = User::create([
            'name' => 'Kojo Mensah',
            'email' => 'kojo@example.com',
            'phone' => '+233201234567',
            'password_hash' => Hash::make('StrongPass1!'),
            'is_admin' => false,
            'is_active' => true,
        ]);
        Appointment::create([
            'user_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'appointment_date' => '2026-12-01',
            'appointment_time' => '10:30:00',
            'appointment_type' => 'eye_exam',
            'status' => 'confirmed',
        ]);
        Appointment::create([
            'user_id' => $otherCustomer->id,
            'name' => $otherCustomer->name,
            'email' => $otherCustomer->email,
            'phone' => $otherCustomer->phone,
            'appointment_date' => '2026-12-02',
            'appointment_time' => '11:00:00',
            'appointment_type' => 'consultation',
            'status' => 'pending',
        ]);

        $this->actingAs($customer)->get(route('account.index'))
            ->assertOk()
            ->assertSee('Eye Exam')
            ->assertDontSee('Consultation');
    }

    public function test_guest_cannot_add_more_items_than_available_stock(): void
    {
        $product = $this->product(stock: 2);

        $this->from(route('cart.index'))->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseMissing('cart', ['product_id' => $product->id]);
    }

    public function test_guest_can_add_an_available_product_to_cart(): void
    {
        $product = $this->product(stock: 2);

        $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('cart', [
            'product_id' => $product->id,
            'quantity' => 1,
            'user_id' => null,
        ]);
    }

    public function test_checkout_reserves_stock_and_creates_order_snapshot(): void
    {
        $customer = $this->customer();
        $product = $this->product(stock: 3);
        DB::table('cart')->insert([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $checkoutToken = (string) Str::uuid();

        $this->actingAs($customer)
            ->withSession(['checkout_token' => $checkoutToken])
            ->post(route('checkout.store'), [
                'checkout_token' => $checkoutToken,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => '0241234567',
                'address' => '12 Oxford Street',
                'city' => 'Accra',
                'region' => 'Greater Accra',
            ])
            ->assertRedirect(route('checkout.pending', Order::first()));

        $order = Order::with('items')->firstOrFail();
        $this->assertSame('pending', $order->payment_status);
        $this->assertTrue($order->stock_reserved);
        $this->assertNotNull($order->reservation_expires_at);
        $this->assertSame(1, (int) $product->fresh()->stock);
        $this->assertSame(2, (int) $order->items->first()->quantity);
        $this->assertSame('Classic Frame', $order->items->first()->product_name);
        $this->assertDatabaseMissing('cart', ['user_id' => $customer->id]);
    }

    public function test_paystack_initialization_uses_verified_checkout_endpoint(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/example',
                    'access_code' => 'access-code',
                    'reference' => 'paystack-reference',
                ],
            ]),
        ]);

        $this->actingAs($customer)
            ->withSession(['checkout_order_id' => $order->id])
            ->post(route('checkout.pay', $order))
            ->assertRedirect('https://checkout.paystack.com/example');

        $this->assertSame('paystack-reference', $order->fresh()->paystack_reference);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-secret'));
    }

    public function test_paystack_callback_confirms_matching_payment_once(): void
    {
        $customer = $this->customer();
        $product = $this->product(stock: 1);
        $order = $this->order($customer, [
            'paystack_reference' => 'verified-reference',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'quantity' => 1,
        ]);
        Http::fake([
            'api.paystack.co/transaction/verify/verified-reference' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => 'verified-reference',
                    'amount' => 50000,
                    'currency' => 'GHS',
                    'metadata' => [
                        'order_id' => $order->id,
                        'tracking_code' => $order->tracking_code,
                    ],
                ],
            ]),
        ]);

        $this->actingAs($customer)
            ->withSession(['checkout_order_id' => $order->id])
            ->get(route('payments.paystack.callback', ['reference' => 'verified-reference']))
            ->assertRedirect(route('orders.confirmation', ['tracking_code' => $order->tracking_code]));

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertFalse($order->fresh()->stock_reserved);
        $this->assertSame(1, (int) $product->fresh()->stock);
        $this->get(route('payments.paystack.callback', ['reference' => 'verified-reference']))
            ->assertRedirect(route('orders.confirmation', ['tracking_code' => $order->tracking_code]));
        $this->assertSame(1, (int) $product->fresh()->stock);
        $this->withSession(['confirmed_order_id' => $order->id])
            ->get(route('orders.confirmation', ['tracking_code' => $order->tracking_code]))
            ->assertOk()
            ->assertSee($order->tracking_code);
    }

    public function test_expired_checkout_reservation_releases_stock(): void
    {
        $customer = $this->customer();
        $product = $this->product(stock: 0);
        $order = $this->order($customer, [
            'reservation_expires_at' => now()->subMinute(),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'quantity' => 2,
        ]);

        $this->artisan('bealet:release-expired-checkout-reservations')->assertExitCode(0);

        $this->assertSame(2, (int) $product->fresh()->stock);
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertFalse($order->fresh()->stock_reserved);
    }

    public function test_abandoned_payment_callback_releases_reserved_stock(): void
    {
        $customer = $this->customer();
        $product = $this->product(stock: 0);
        $order = $this->order($customer, [
            'paystack_reference' => 'abandoned-reference',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'quantity' => 1,
        ]);
        Http::fake([
            'api.paystack.co/transaction/verify/abandoned-reference' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'abandoned',
                    'reference' => 'abandoned-reference',
                    'metadata' => [
                        'order_id' => $order->id,
                        'tracking_code' => $order->tracking_code,
                    ],
                ],
            ]),
        ]);

        $this->actingAs($customer)
            ->withSession(['checkout_order_id' => $order->id])
            ->get(route('payments.paystack.callback', ['reference' => 'abandoned-reference']))
            ->assertRedirect(route('checkout.index'));

        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertFalse($order->fresh()->stock_reserved);
        $this->assertSame(1, (int) $product->fresh()->stock);
    }

    public function test_order_confirmation_cannot_be_viewed_without_session_or_account_ownership(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);

        $this->get(route('orders.confirmation', ['tracking_code' => $order->tracking_code]))->assertNotFound();
    }

    private function customer(): User
    {
        return User::create([
            'name' => 'Ama Mensah',
            'email' => 'ama@example.com',
            'phone' => '+233241234567',
            'password_hash' => Hash::make('StrongPass1!'),
            'is_admin' => false,
            'is_active' => true,
        ]);
    }

    private function product(int $stock): Product
    {
        return Product::create([
            'name' => 'Classic Frame',
            'slug' => 'classic-frame',
            'category' => 'frames',
            'price' => 500,
            'stock' => $stock,
            'is_active' => true,
        ]);
    }

    private function order(User $customer, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $customer->id,
            'guest_name' => $customer->name,
            'guest_email' => $customer->email,
            'order_phone' => '+233241234567',
            'tracking_code' => 'BOC/'.Str::upper(Str::random(6)).'/2026/AM',
            'subtotal' => 500,
            'shipping_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total_amount' => 500,
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_address' => '12 Oxford Street, Accra',
            'shipping_city' => 'Accra',
            'shipping_state' => 'Greater Accra',
            'shipping_country' => 'Ghana',
            'stock_reserved' => true,
            'reservation_expires_at' => now()->addMinutes(30),
        ], $attributes));
    }
}
