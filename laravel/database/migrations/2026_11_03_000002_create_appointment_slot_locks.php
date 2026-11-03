<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_slot_locks', function (Blueprint $table): void {
            $table->id();
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->timestamps();
            $table->unique(['appointment_date', 'appointment_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_slot_locks');
    }
};
