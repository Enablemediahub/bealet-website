<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->index();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('category', 50)->index();
            $table->string('frame_target', 50)->nullable();
            $table->string('brand', 100)->nullable()->index();
            $table->string('frame_material', 50)->nullable();
            $table->string('frame_color', 50)->nullable();
            $table->string('frame_shape', 50)->nullable();
            $table->integer('lens_width')->nullable();
            $table->integer('bridge_width')->nullable();
            $table->integer('temple_length')->nullable();
            $table->string('lens_type', 50)->nullable();
            $table->string('lens_coating', 100)->nullable();
            $table->string('ar_model_2d')->nullable();
            $table->string('ar_model_3d')->nullable();
            $table->integer('ar_position_x')->nullable()->default(0);
            $table->integer('ar_position_y')->nullable()->default(0);
            $table->decimal('ar_scale', 3, 2)->nullable()->default(1);
            $table->integer('stock')->nullable()->default(0);
            $table->integer('low_stock_threshold')->nullable()->default(5);
            $table->string('sku', 100)->nullable()->unique();
            $table->string('main_image')->nullable();
            $table->text('gallery_images')->nullable();
            $table->boolean('is_active')->nullable()->default(true)->index();
            $table->boolean('featured')->nullable()->default(false)->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('is_featured')->default(false);
            $table->string('ar_model_2d_left')->nullable();
            $table->string('ar_model_2d_right')->nullable();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone', 20);
            $table->date('appointment_date')->index();
            $table->time('appointment_time');
            $table->enum('appointment_type', ['eye_exam', 'frame_fitting', 'consultation', 'follow_up'])->nullable()->default('eye_exam');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'])->nullable()->default('pending')->index();
            $table->boolean('reminder_sent')->nullable()->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('featured_image')->nullable();
            $table->unsignedInteger('author_id')->nullable()->index();
            $table->integer('views')->nullable()->default(0);
            $table->boolean('is_published')->nullable()->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('cart', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('session_id')->nullable()->index();
            $table->integer('user_id')->nullable()->index();
            $table->integer('product_id')->index();
            $table->integer('quantity')->default(1);
            $table->integer('prescription_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('company_branches', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('branch_name');
            $table->string('phone_primary', 50)->nullable();
            $table->string('phone_secondary', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('google_maps_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone', 20)->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->boolean('is_read')->nullable()->default(false)->index();
            $table->boolean('replied')->nullable()->default(false);
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('customer_reviews', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id')->nullable()->unique();
            $table->string('reviewer_name');
            $table->string('reviewer_email')->nullable();
            $table->string('profile_image')->nullable();
            $table->unsignedTinyInteger('rating')->default(5)->index();
            $table->text('comment');
            $table->boolean('is_approved')->default(false)->index();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('founder_gallery', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('item_title');
            $table->string('item_type', 40)->default('portrait');
            $table->text('item_description')->nullable();
            $table->string('image_path');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('gamehub_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('player_id')->index();
            $table->unsignedInteger('question_id')->index();
            $table->string('selected_answer');
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('points_awarded')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent()->index();
        });

        Schema::create('gamehub_players', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->char('device_token', 64)->unique();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->unsignedInteger('total_score')->default(0)->index();
            $table->unsignedInteger('sessions')->default(0);
            $table->timestamp('last_played_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('gamehub_questions', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->enum('game_type', ['quiz', 'spot', 'part', 'acuity'])->index();
            $table->text('question');
            $table->string('correct_answer');
            $table->string('option_a');
            $table->string('option_b');
            $table->string('option_c');
            $table->string('option_d');
            $table->string('visual_value')->nullable();
            $table->unsignedTinyInteger('points')->default(10);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('gamehub_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('points_goal')->default(80);
            $table->string('reward_title')->default('Free eye examination');
            $table->boolean('is_active')->default(true);
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('newsletter', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('email')->unique();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('subscribed_at')->useCurrent();
            $table->boolean('is_active')->nullable()->default(true)->index();
            $table->string('unsubscribe_token')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('order_id')->index();
            $table->integer('product_id')->index();
            $table->string('product_name');
            $table->decimal('product_price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->integer('prescription_id')->nullable();
        });

        Schema::create('order_prescriptions', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('order_id')->index();
            $table->enum('prescription_source', ['manual', 'upload', 'camera', 'manual_upload'])->default('manual');
            $table->string('frame_notes')->nullable();
            $table->longText('manual_prescription')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->text('customer_notes')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id')->nullable()->index();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable()->index();
            $table->string('order_phone', 30)->nullable();
            $table->string('guest_phone', 20)->nullable();
            $table->string('tracking_code', 50)->unique();
            $table->string('paystack_reference', 100)->nullable()->unique();
            $table->string('paystack_access_code', 100)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_fee', 10, 2)->nullable()->default(0);
            $table->decimal('tax', 10, 2)->nullable()->default(0);
            $table->decimal('discount', 10, 2)->nullable()->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'])->nullable()->default('pending')->index();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->nullable()->default('pending')->index();
            $table->text('shipping_address');
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_state', 100)->nullable();
            $table->string('shipping_zip', 20)->nullable();
            $table->string('shipping_country', 100)->nullable()->default('Nigeria');
            $table->string('tracking_number', 100)->nullable();
            $table->string('courier_name', 100)->nullable();
            $table->date('estimated_delivery')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('prescription_details', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id')->nullable()->index();
            $table->integer('order_id')->nullable()->index();
            $table->decimal('od_sphere', 4, 2)->nullable();
            $table->decimal('od_cylinder', 4, 2)->nullable();
            $table->integer('od_axis')->nullable();
            $table->decimal('od_add', 4, 2)->nullable();
            $table->decimal('os_sphere', 4, 2)->nullable();
            $table->decimal('os_cylinder', 4, 2)->nullable();
            $table->integer('os_axis')->nullable();
            $table->decimal('os_add', 4, 2)->nullable();
            $table->decimal('pupillary_distance', 5, 2)->nullable();
            $table->string('prescription_image')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('product_id')->index();
            $table->string('image_path');
            $table->unsignedTinyInteger('image_slot');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('product_id')->index();
            $table->integer('user_id')->nullable()->index();
            $table->string('reviewer_name')->nullable();
            $table->string('reviewer_email')->nullable();
            $table->integer('order_id')->nullable()->index();
            $table->integer('rating')->index();
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->string('review_image')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_verified_purchase')->nullable()->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('setting_key', 100)->unique();
            $table->text('setting_value')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('staff_members', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('name');
            $table->string('designation');
            $table->integer('branch_id')->nullable();
            $table->string('email')->nullable();
            $table->string('contact', 50)->nullable();
            $table->string('thumbnail')->nullable();
            $table->text('bio')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('tracking_history', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('order_id')->index();
            $table->string('status', 50);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('wishlist', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('user_id')->index();
            $table->integer('product_id')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach ([
            'wishlist',
            'tracking_history',
            'staff_members',
            'site_settings',
            'reviews',
            'product_images',
            'prescription_details',
            'orders',
            'order_prescriptions',
            'order_items',
            'newsletter',
            'gamehub_settings',
            'gamehub_questions',
            'gamehub_players',
            'gamehub_attempts',
            'founder_gallery',
            'customer_reviews',
            'contacts',
            'company_branches',
            'cart',
            'blog_posts',
            'appointments',
            'products',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
