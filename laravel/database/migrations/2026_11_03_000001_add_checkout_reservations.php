<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('checkout_token', 64)->nullable()->unique();
            $table->boolean('stock_reserved')->default(false);
            $table->timestamp('reservation_expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['checkout_token']);
            $table->dropIndex(['reservation_expires_at']);
            $table->dropColumn(['checkout_token', 'stock_reserved', 'reservation_expires_at']);
        });
    }
};
