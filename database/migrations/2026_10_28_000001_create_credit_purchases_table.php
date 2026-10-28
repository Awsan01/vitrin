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
        Schema::create('credit_purchases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pack', 30);
            $table->string('price_id', 50);
            $table->unsignedInteger('credits');
            $table->unsignedInteger('credits_reversed')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->string('paddle_transaction_id', 50)->nullable()->unique();
            $table->string('paddle_customer_id', 50)->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->char('currency', 3)->nullable();
            $table->json('adjustment_ids')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('credit_transactions', function (Blueprint $table) {
            // Ties a ledger entry to an outside event (a Paddle transaction or
            // adjustment), so the same payment can never be applied twice.
            $table->string('reference', 100)->nullable()->unique()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });

        Schema::dropIfExists('credit_purchases');
    }
};
