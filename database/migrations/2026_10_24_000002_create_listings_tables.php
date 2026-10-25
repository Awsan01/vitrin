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
        Schema::create('listings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->text('notes')->nullable();
            $table->string('source_path');
            $table->string('status', 20)->default('queued')->index();
            $table->json('product_facts')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('listing_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('listing_id')->constrained()->cascadeOnDelete();
            $table->string('marketplace', 30);
            $table->string('language', 5);
            $table->unsignedSmallInteger('cost')->default(0);
            $table->string('status', 20)->default('pending');
            $table->text('title')->nullable();
            $table->text('highlight')->nullable();
            $table->json('bullets')->nullable();
            $table->text('description')->nullable();
            $table->json('keywords')->nullable();
            $table->string('category')->nullable();
            $table->json('warnings')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['listing_id', 'marketplace', 'language']);
        });

        Schema::create('listing_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('listing_id')->constrained()->cascadeOnDelete();
            $table->string('style', 30);
            $table->unsignedSmallInteger('cost')->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('path')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['listing_id', 'style']);
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('amount');
            $table->unsignedInteger('balance_after');
            $table->string('reason', 30);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('listing_photos');
        Schema::dropIfExists('listing_copies');
        Schema::dropIfExists('listings');
    }
};
