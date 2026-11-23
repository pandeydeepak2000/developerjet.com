<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Foundation tables for the customer dashboard. No payment or provisioning logic exists yet:
// rows will only be created by future, verified workflows.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30)->unique();
            $table->string('status', 30)->default('pending_payment');
            $table->string('currency', 3)->default('INR');
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->json('snapshot')->nullable(); // purchase-time plan/price/config snapshot (FR-017)
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('status', 30)->default('pending_provisioning');
            $table->timestamp('renews_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->nullable()->unique(); // assigned only on issue
            $table->string('status', 20)->default('draft');
            $table->string('currency', 3)->default('INR');
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->default('INR');
            $table->bigInteger('balance_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // credit | debit
            $table->unsignedBigInteger('amount_minor');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('services');
        Schema::dropIfExists('orders');
    }
};
