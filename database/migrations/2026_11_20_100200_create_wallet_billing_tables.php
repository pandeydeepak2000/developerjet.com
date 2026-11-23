<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('reference', 120)->nullable()->unique(); // idempotency key: prevents double credit/debit
            $table->string('source', 30)->default('system'); // topup | order | refund | admin_adjustment
            $table->bigInteger('balance_after_minor')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
        });

        Schema::create('topup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('payment_reference')->nullable(); // customer-supplied (e.g. UTR); never trusted as proof
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending'); // pending | credited | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 12)->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('billing_interval', 10)->default('monthly');
            $table->string('status', 20)->default('active'); // active | past_due | cancelled
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->foreignId('renewal_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('type', 15)->default('invoice'); // invoice | credit_note
            $table->foreignId('related_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('billing_snapshot')->nullable(); // company + customer details frozen at issue time
            $table->json('payment_info')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->unique(['order_id', 'type'], 'invoices_order_type_unique');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedBigInteger('unit_minor');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_order_type_unique');
            $table->dropConstrainedForeignId('related_invoice_id');
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn(['type', 'subtotal_minor', 'discount_minor', 'tax_minor', 'due_at', 'paid_at', 'billing_snapshot', 'payment_info', 'emailed_at']);
        });
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('topup_requests');
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actor_id');
            $table->dropColumn(['reference', 'source', 'balance_after_minor', 'meta']);
        });
    }
};
