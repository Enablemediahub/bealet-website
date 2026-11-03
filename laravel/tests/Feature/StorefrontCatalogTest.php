<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\CustomerReview;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Support\ContentAsset;
use App\Support\WhatsAppLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_featured_active_products(): void
    {
        Product::create([
            'name' => 'Featured Frame',
            'slug' => 'featured-frame',
            'category' => 'frames',
            'price' => 250,
            'stock' => 4,
            'is_active' => true,
            'is_featured' => true,
        ]);
        Product::create([
            'name' => 'Hidden Frame',
            'slug' => 'hidden-frame',
            'category' => 'frames',
            'price' => 280,
            'is_active' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('BEALET OPTICAL CENTER')
            ->assertSee('Featured Frame')
            ->assertDontSee('Hidden Frame');
    }

    public function test_homepage_hero_uses_configured_wallpaper_with_overlaid_navigation(): void
    {
        SiteSetting::create([
            'setting_key' => 'home_hero_image',
            'setting_value' => 'assets/uploads/hero/homepage-wallpaper.jpg',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('site-header-wrap--hero', false)
            ->assertSee('--storefront-hero-image: url(', false)
            ->assertSee('/legacy-assets/uploads/hero/homepage-wallpaper.jpg', false)
            ->assertSee('storefront-hero-carousel', false)
            ->assertSee('storefront-hero-overlay', false)
            ->assertSee('storefront-hero-slide--zoom', false)
            ->assertSee('storefront-hero-slide--pan-left', false)
            ->assertSee('storefront-hero-slide--pan-right', false)
            ->assertSee('storefront-hero-copy', false);
    }

    public function test_legacy_blog_image_filenames_resolve_to_the_blog_upload_directory(): void
    {
        $post = BlogPost::create([
            'title' => 'Blog image guide',
            'slug' => 'blog-image-guide',
            'content' => 'A guide with a legacy featured image.',
            'featured_image' => '69e79c8ac34cc_1776786570.jpg',
            'is_published' => true,
        ]);

        $imageUrl = asset('legacy-assets/uploads/blog/69e79c8ac34cc_1776786570.jpg');
        $this->assertSame($imageUrl, ContentAsset::blogUrl($post->featured_image));

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('src="'.$imageUrl.'"', false);
    }

    public function test_storefront_navigation_highlights_the_current_route_only(): void
    {
        $reviewsResponse = $this->get(route('reviews.index'))->assertOk();
        $this->assertMatchesRegularExpression(
            '/<a class="[^"]*\bactive\b[^"]*"[^>]*>Reviews<\/a>/',
            $reviewsResponse->getContent(),
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<a class="[^"]*\bactive\b[^"]*"[^>]*>Appointments<\/a>/',
            $reviewsResponse->getContent(),
        );

        $appointmentsResponse = $this->get(route('appointments.create'))->assertOk();
        $this->assertMatchesRegularExpression(
            '/<a class="[^"]*\bactive\b[^"]*"[^>]*>Appointments<\/a>/',
            $appointmentsResponse->getContent(),
        );
    }

    public function test_product_inquiries_open_whatsapp_with_product_and_thumbnail_details(): void
    {
        SiteSetting::create(['setting_key' => 'whatsapp_phone', 'setting_value' => '0241234567']);
        $product = Product::create([
            'name' => 'WhatsApp Inquiry Frame',
            'slug' => 'whatsapp-inquiry-frame',
            'description' => 'A lightweight acetate frame.',
            'category' => 'frames',
            'price' => 250,
            'stock' => 4,
            'main_image' => 'assets/uploads/products/inquiry-frame.jpg',
            'is_active' => true,
        ]);

        $url = WhatsAppLink::productInquiry($product, '0241234567', 'BEALET OPTICAL CENTER');
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

        $this->assertSame('/233241234567', parse_url((string) $url, PHP_URL_PATH));
        $this->assertStringContainsString($product->name, $query['text']);
        $this->assertStringContainsString(route('shop.show', $product->slug), $query['text']);
        $this->assertStringContainsString($product->image_url, $query['text']);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('Ask on WhatsApp')
            ->assertDontSee('Add to cart')
            ->assertDontSee('Shopping cart')
            ->assertSee('storefront-product-actions', false)
            ->assertSee('href="'.$url.'"', false);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Ask on WhatsApp')
            ->assertDontSee('Add to cart')
            ->assertSee('href="'.$url.'"', false)
            ->assertSee('property="og:image" content="'.$product->image_url.'"', false);
    }

    public function test_shared_footer_shows_legacy_sections_hours_and_configured_links(): void
    {
        SiteSetting::create([
            'setting_key' => 'facebook_url',
            'setting_value' => 'https://facebook.com/bealet',
        ]);
        SiteSetting::create([
            'setting_key' => 'whatsapp_phone',
            'setting_value' => '0241234567',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Quick Links')
            ->assertSee('Customer Service')
            ->assertSee('Newsletter')
            ->assertSee('Monday - Friday: 8:00 AM - 5:00 PM')
            ->assertSee('https://facebook.com/bealet')
            ->assertSee('https://wa.me/233241234567');
    }

    public function test_homepage_restores_legacy_sections_reviews_and_featured_product_slideshows(): void
    {
        SiteSetting::create(['setting_key' => 'founder_name', 'setting_value' => 'Ama Bealet']);
        SiteSetting::create(['setting_key' => 'founder_short_bio', 'setting_value' => 'Founder bio for homepage']);
        SiteSetting::create(['setting_key' => 'founder_quote', 'setting_value' => 'Vision for everyone']);
        SiteSetting::create([
            'setting_key' => 'intro_video',
            'setting_value' => 'assets/uploads/branding/intro.mp4',
        ]);

        $product = Product::create([
            'name' => 'Featured Gallery Frame',
            'slug' => 'featured-gallery-frame',
            'category' => 'frames',
            'price' => 250,
            'stock' => 4,
            'main_image' => 'assets/uploads/products/frame-main.jpg',
            'is_active' => true,
            'is_featured' => true,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'assets/uploads/products/frame-side.jpg',
            'image_slot' => 1,
        ]);

        CustomerReview::create([
            'reviewer_name' => 'Ama Customer',
            'rating' => 5,
            'comment' => 'A wonderful optical experience from the team.',
            'is_approved' => true,
        ]);
        Review::create([
            'product_id' => $product->id,
            'reviewer_name' => 'Kojo Reviewer',
            'rating' => 4,
            'comment' => 'The frame is comfortable and looks very good.',
            'is_approved' => true,
        ]);
        BlogPost::create([
            'title' => 'Choosing the right frames',
            'slug' => 'choosing-the-right-frames',
            'content' => 'A guide to choosing frames that fit.',
            'is_published' => true,
        ]);

        $this        ->get('/')
        ->assertOk()
        ->assertSee('Why Choose Bealet?')
        ->assertSee('Featured Products')
            ->assertSee('featured-product-gallery-carousel', false)
            ->assertSee('data-bs-interval="2400"', false)
            ->assertSee('What Customers Are Saying')
            ->assertSee('siteIntroOverlay')
            ->assertSee('siteIntroVideo')
            ->assertSee('/legacy-assets/uploads/branding/intro.mp4')
            ->assertSee('homepageReviewToast')
            ->assertSee('setTimeout(hideToast, 7000)')
            ->assertSee('scheduleNext(14000)')
            ->assertSee('data-review-reveal', false)
            ->assertSee('IntersectionObserver')
            ->assertSee('Ama Customer')
            ->assertSee('Kojo Reviewer')
            ->assertSee('4.5/5')
            ->assertSee('Founder Spotlight')
            ->assertSee('Ama Bealet')
            ->assertSee('Our Services')
            ->assertSee('Latest from Our Blog')
            ->assertSee('Choosing the right frames')
            ->assertSee('Ready to Experience Better Vision?');
    }

    public function test_shop_search_and_category_filters_only_show_matching_active_products(): void
    {
        Product::create([
            'name' => 'Classic Blue Frame',
            'slug' => 'classic-blue-frame',
            'description' => 'Lightweight acetate eyewear',
            'category' => 'frames',
            'brand' => 'Bealet',
            'price' => 300,
            'is_active' => true,
        ]);
        Product::create([
            'name' => 'Clear Lens',
            'slug' => 'clear-lens',
            'category' => 'lenses',
            'price' => 120,
            'is_active' => true,
        ]);
        Product::create([
            'name' => 'Try-On Frame',
            'slug' => 'try-on-frame',
            'category' => 'frames',
            'price' => 350,
            'ar_model_2d' => 'assets/uploads/ar-models/frame.png',
            'is_active' => true,
        ]);

        $this->get('/shop?search=classic&category=frames')
            ->assertOk()
            ->assertSee('Our Collections')
            ->assertSee('Filters')
            ->assertSee('name="category"', false)
            ->assertSee('Classic Blue Frame')
            ->assertDontSee('Clear Lens');

        $this->get('/shop?category=frames&min_price=200&max_price=400&try_on=ready&sort=price_low')
            ->assertOk()
            ->assertSee('Try-On Frame')
            ->assertDontSee('Clear Lens');
    }

    public function test_shop_pagination_uses_bootstrap_controls_instead_of_unstyled_tailwind_svgs(): void
    {
        foreach (range(1, 13) as $number) {
            Product::create([
                'name' => "Frame {$number}",
                'slug' => "frame-{$number}",
                'category' => 'frames',
                'price' => 100 + $number,
                'is_active' => true,
            ]);
        }

        $this->get('/shop')
            ->assertOk()
            ->assertSee('<ul class="pagination">', false)
            ->assertDontSee('viewBox="0 0 20 20"', false);
    }

    public function test_shop_detail_route_and_legacy_product_link_work(): void
    {
        $product = Product::create([
            'name' => 'Heritage Frame',
            'slug' => 'heritage-frame',
            'description' => 'A lightweight everyday frame.',
            'category' => 'premium',
            'price' => 525,
            'stock' => 2,
            'is_active' => true,
        ]);
        Product::create([
            'name' => 'Another Heritage Frame',
            'slug' => 'another-heritage-frame',
            'category' => 'premium',
            'price' => 400,
            'is_active' => true,
        ]);

        $this->get('/shop/heritage-frame')
            ->assertOk()
            ->assertSee('Heritage Frame')
            ->assertSee('A lightweight everyday frame.')
            ->assertSee('Another Heritage Frame')
            ->assertSee('Price on enquiry');

        $this->get('/shop.php?view_product='.$product->id)
            ->assertRedirect('/shop/heritage-frame');
    }
}
