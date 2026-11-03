<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\ContentAdminController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OperationsAdminController;
use App\Http\Controllers\Admin\SiteContentAdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\TryOnController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/shop', [StorefrontController::class, 'index'])->name('shop.index');
Route::get('/shop.php', [StorefrontController::class, 'index'])->name('shop.legacy');
Route::get('/shop/{slug}', [StorefrontController::class, 'show'])->name('shop.show');
Route::get('/ar-tryon', [TryOnController::class, 'index'])->name('tryon.index');
Route::redirect('/ar-tryon.php', '/ar-tryon', 301);
Route::redirect('/appointment.php', '/appointments', 301);
Route::redirect('/my-appointments.php', '/my-appointments', 301);
Route::redirect('/contact.php', '/contact', 301);
Route::redirect('/reviews.php', '/reviews', 301);
Route::redirect('/blog.php', '/blog', 301);
Route::redirect('/team.php', '/team', 301);
Route::get('/blog-post.php', function (Request $request) {
    return redirect()->route('blog.show', ['slug' => (string) $request->query('slug', '')], 301);
});
Route::get('/appointments', [AppointmentController::class, 'create'])->name('appointments.create');
Route::post('/appointments', [AppointmentController::class, 'store'])->middleware('throttle:6,1')->name('appointments.store');
Route::get('/my-appointments', [AppointmentController::class, 'index'])->middleware('auth')->name('appointments.index');
Route::patch('/my-appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->middleware('auth')->name('appointments.cancel');
Route::get('/contact', [ContactController::class, 'index'])->name('contact.index');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::post('/newsletter', [NewsletterController::class, 'store'])->middleware('throttle:6,1')->name('newsletter.store');
Route::post('/api/subscribe-newsletter', [NewsletterController::class, 'store'])
    ->middleware('throttle:6,1')
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('newsletter.api');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
Route::post('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'confirmUnsubscribe'])->name('newsletter.unsubscribe.confirm');
Route::get('/blog', [ContentController::class, 'blog'])->name('blog.index');
Route::get('/blog/{slug}', [ContentController::class, 'blogPost'])->name('blog.show');
Route::get('/reviews', [ContentController::class, 'reviews'])->name('reviews.index');
Route::post('/reviews', [ContentController::class, 'storeCustomerReview'])->middleware('throttle:5,1')->name('reviews.store');
Route::post('/product-reviews', [ContentController::class, 'storeProductReview'])->middleware('throttle:5,1')->name('product-reviews.store');
Route::get('/team', [ContentController::class, 'team'])->name('team');
Route::get('/our-story', [ContentController::class, 'founder'])->name('founder');
Route::redirect('/login.php', '/login', 301);
Route::redirect('/register.php', '/register', 301);
Route::redirect('/checkout.php', '/checkout', 301);
Route::get('/my-orders.php', fn () => redirect()->route('account.orders'))->middleware('auth');
Route::get('/profile.php', fn () => redirect()->route('account.index'))->middleware('auth');
Route::get('/order-confirmation.php', function (Request $request) {
    $trackingCode = trim((string) $request->query('tracking_code', ''));

    return $trackingCode !== ''
        ? redirect()->route('orders.confirmation', ['tracking_code' => $trackingCode], 301)
        : redirect()->route('shop.index', [], 301);
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AccountController::class, 'showLogin'])->name('login');
    Route::post('/login', [AccountController::class, 'login'])->middleware('throttle:8,1')->name('login.store');
    Route::get('/register', [AccountController::class, 'showRegister'])->name('register');
    Route::post('/register', [AccountController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/forgot-password', [AccountController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AccountController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AccountController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AccountController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.update');
});

Route::post('/logout', [AccountController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->prefix('account')->name('account.')->group(function (): void {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::patch('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::put('/password', [AccountController::class, 'updatePassword'])->name('password.update');
    Route::get('/orders', [AccountController::class, 'orders'])->name('orders');
    Route::get('/orders/{order}', [AccountController::class, 'showOrder'])->name('orders.show');
});

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/checkout/{order}/pending', [CheckoutController::class, 'showPendingPayment'])->name('checkout.pending');
Route::post('/checkout/{order}/pay', [PaymentController::class, 'start'])->middleware('throttle:6,1')->name('checkout.pay');
Route::get('/payments/paystack/callback', [PaymentController::class, 'callback'])->middleware('throttle:20,1')->name('payments.paystack.callback');
Route::get('/orders/confirmation', [PaymentController::class, 'confirmation'])->middleware('throttle:20,1')->name('orders.confirmation');

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureAdmin::class])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/orders', [OperationsAdminController::class, 'orders'])->name('orders.index');
    Route::get('/orders/{order}', [OperationsAdminController::class, 'showOrder'])->name('orders.show');
    Route::patch('/orders/{order}', [OperationsAdminController::class, 'updateOrder'])->name('orders.update');
    Route::get('/inventory', [OperationsAdminController::class, 'inventory'])->name('inventory.index');
    Route::patch('/inventory/{product}', [OperationsAdminController::class, 'updateInventory'])->name('inventory.update');
    Route::get('/customers', [OperationsAdminController::class, 'customers'])->name('customers.index');
    Route::get('/contacts', [ContentAdminController::class, 'contacts'])->name('content.contacts');
    Route::patch('/contacts/{contact}', [ContentAdminController::class, 'updateContact'])->name('content.contacts.update');
    Route::get('/appointments', [ContentAdminController::class, 'appointments'])->name('content.appointments');
    Route::patch('/appointments/{appointment}', [ContentAdminController::class, 'updateAppointment'])->name('content.appointments.update');
    Route::get('/reviews', [ContentAdminController::class, 'reviews'])->name('content.reviews');
    Route::patch('/reviews/customer/{review}', [ContentAdminController::class, 'updateCustomerReview'])->name('content.reviews.customer.update');
    Route::patch('/reviews/product/{review}', [ContentAdminController::class, 'updateProductReview'])->name('content.reviews.product.update');
    Route::get('/blog', [ContentAdminController::class, 'blog'])->name('content.blog');
    Route::get('/blog/create', [ContentAdminController::class, 'createBlogPost'])->name('content.blog.create');
    Route::post('/blog', [ContentAdminController::class, 'storeBlogPost'])->name('content.blog.store');
    Route::get('/blog/{post}/edit', [ContentAdminController::class, 'editBlogPost'])->name('content.blog.edit');
    Route::put('/blog/{post}', [ContentAdminController::class, 'updateBlogPost'])->name('content.blog.update');
    Route::delete('/blog/{post}', [ContentAdminController::class, 'destroyBlogPost'])->name('content.blog.destroy');
    Route::middleware(EnsureSuperAdmin::class)->group(function (): void {
        Route::get('/products', [OperationsAdminController::class, 'products'])->name('products.index');
        Route::get('/products/create', [OperationsAdminController::class, 'createProduct'])->name('products.create');
        Route::post('/products', [OperationsAdminController::class, 'storeProduct'])->name('products.store');
        Route::get('/products/{product}/edit', [OperationsAdminController::class, 'editProduct'])->name('products.edit');
        Route::put('/products/{product}', [OperationsAdminController::class, 'updateProduct'])->name('products.update');
        Route::patch('/products/{product}/deactivate', [OperationsAdminController::class, 'deactivateProduct'])->name('products.deactivate');
        Route::get('/site/settings', [SiteContentAdminController::class, 'settings'])->name('site.settings');
        Route::put('/site/settings', [SiteContentAdminController::class, 'updateSettings'])->name('site.settings.update');
        Route::post('/site/branches', [SiteContentAdminController::class, 'storeBranch'])->name('site.branches.store');
        Route::put('/site/branches/{branch}', [SiteContentAdminController::class, 'updateBranch'])->name('site.branches.update');
        Route::patch('/site/branches/{branch}/deactivate', [SiteContentAdminController::class, 'deactivateBranch'])->name('site.branches.deactivate');
        Route::get('/site/staff', [SiteContentAdminController::class, 'staff'])->name('site.staff');
        Route::post('/site/staff', [SiteContentAdminController::class, 'storeStaff'])->name('site.staff.store');
        Route::put('/site/staff/{staffMember}', [SiteContentAdminController::class, 'updateStaff'])->name('site.staff.update');
        Route::patch('/site/staff/{staffMember}/deactivate', [SiteContentAdminController::class, 'deactivateStaff'])->name('site.staff.deactivate');
        Route::get('/site/gallery', [SiteContentAdminController::class, 'gallery'])->name('site.gallery');
        Route::post('/site/gallery', [SiteContentAdminController::class, 'storeGalleryItem'])->name('site.gallery.store');
        Route::put('/site/gallery/{founderGallery}', [SiteContentAdminController::class, 'updateGalleryItem'])->name('site.gallery.update');
        Route::patch('/site/gallery/{founderGallery}/deactivate', [SiteContentAdminController::class, 'deactivateGalleryItem'])->name('site.gallery.deactivate');
    });
});
