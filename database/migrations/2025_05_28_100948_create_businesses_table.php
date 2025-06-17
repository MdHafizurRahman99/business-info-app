<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('place_id')->unique()->index();
            $table->string('name')->nullable(false);
            $table->text('category')->nullable();
            $table->text('address')->nullable(false);
            $table->string('postcode', 10)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->decimal('google_rating', 2, 1)->nullable();
            $table->integer('user_ratings_total')->nullable();
            $table->decimal('latitude', 10, 8)->nullable(false);
            $table->decimal('longitude', 11, 8)->nullable(false);
            $table->timestamps();

            // Additional indexes for performance
            $table->index(['latitude', 'longitude']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
