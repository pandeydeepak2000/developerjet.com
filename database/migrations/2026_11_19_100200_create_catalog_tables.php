<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A resource profile describes compute resources, separate from the saleable plan (SRS 5.1).
        Schema::create('resource_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('vcpu')->nullable();
            $table->unsignedSmallInteger('ram_gb')->nullable();
            $table->unsignedSmallInteger('disk_gb')->nullable();
            $table->string('disk_type', 20)->nullable();
            $table->unsignedSmallInteger('bandwidth_tb')->nullable();
            // displayed = shown on the existing site but not verified by the owner; pending = unknown.
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained('product_categories');
            $table->foreignId('resource_profile_id')->nullable()->constrained('resource_profiles')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->string('highlight')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            // Only purchasable plans with a price can enter a cart.
            $table->boolean('is_purchasable')->default(false);
            // approved | illustrative (shown on existing site, not owner-approved) | pending
            $table->string('price_status', 20)->default('pending');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('billing_interval', 10); // monthly | yearly
            $table->unsignedBigInteger('amount_minor')->nullable(); // paise; null = not set
            $table->string('currency', 3)->default('INR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'billing_interval']);
        });

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->string('billing_type', 20)->default('recurring'); // recurring | one_time
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('addon_plan', function (Blueprint $table) {
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->primary(['addon_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_plan');
        Schema::dropIfExists('addons');
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('resource_profiles');
        Schema::dropIfExists('product_categories');
    }
};
