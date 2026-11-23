<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->json('not_included')->nullable();
            $table->json('comparison')->nullable();
            $table->unsignedBigInteger('setup_fee_minor')->default(0);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->string('tax_class', 30)->default('standard');
            $table->string('visibility', 20)->default('public'); // public | hidden
            $table->integer('stock')->nullable(); // null = unlimited
            $table->string('fulfilment', 20)->default('provisioned'); // provisioned | quote
            $table->string('kind', 30)->default('hosting'); // hosting | wordpress | laravel | web | seo
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('wallet_applied_minor')->default(0);
            $table->string('payment_method', 20)->nullable();
            $table->json('customer_snapshot')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('billing_interval', 10)->default('monthly');
            $table->unsignedBigInteger('unit_minor');
            $table->unsignedBigInteger('setup_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->json('config')->nullable(); // plan / resources / add-ons / customer configuration snapshot
            $table->timestamps();
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30); // status | note | payment | assignment
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->string('mode', 10)->default('test');
            $table->string('status', 20)->default('created'); // created | verified | failed | amount_mismatch
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('INR');
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('gateway_payment_id')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'gateway_payment_id']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 20);
            $table->string('event_id', 120);
            $table->string('event_type', 80)->nullable();
            $table->string('outcome', 30)->default('received'); // received | processed | ignored | rejected
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'event_id']); // idempotent webhook processing
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_items');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['idempotency_key', 'subtotal_minor', 'discount_minor', 'tax_minor', 'wallet_applied_minor', 'payment_method', 'customer_snapshot', 'paid_at', 'cancelled_at', 'cancel_reason']);
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['description', 'not_included', 'comparison', 'setup_fee_minor', 'discount_percent', 'tax_class', 'visibility', 'stock', 'fulfilment', 'kind']);
        });
    }
};
