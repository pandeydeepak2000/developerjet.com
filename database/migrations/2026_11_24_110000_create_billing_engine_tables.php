<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing engine (additive only — no existing row is rewritten):
 *  - plan_prices: per-period discount, setup fee and approval (NULL approval = inherit the plan's price_status,
 *    so every existing price behaves exactly as before).
 *  - subscriptions: agreed-price snapshot, month-end anchor, grace/suspension timestamps, exemption.
 *  - invoices: billing period of a renewal invoice + DB-level idempotency (one invoice per subscription period).
 *  - subscription_events (renewal history), subscription_reminders (dedupe + failure log + retry),
 *    billing_actions (manual suspension/reactivation queue — nothing physical is automated).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_prices', function (Blueprint $table) {
            $table->unsignedTinyInteger('discount_percent')->nullable()->after('currency'); // NULL = plan discount
            $table->unsignedBigInteger('setup_fee_minor')->nullable()->after('discount_percent'); // NULL = plan setup fee
            $table->string('approval_status', 12)->nullable()->after('setup_fee_minor'); // NULL inherit | pending | approved
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('anchor_day')->nullable()->after('billing_interval'); // keeps 31st-of-month cycles stable
            $table->unsignedBigInteger('price_minor')->nullable()->after('anchor_day'); // agreed recurring price (pre-tax, pre-discount)
            $table->unsignedTinyInteger('discount_percent')->default(0)->after('price_minor');
            $table->string('currency', 3)->default('INR')->after('discount_percent');
            $table->string('tax_class', 30)->nullable()->after('currency');
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->boolean('suspension_exempt')->default(false);
            $table->timestamp('last_processed_at')->nullable();
            $table->index(['status', 'next_due_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('billing_interval', 10)->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            // Idempotency: a subscription period can only ever have one invoice (NULLs are distinct, so ordinary
            // order invoices and credit notes are unaffected).
            $table->unique(['subscription_id', 'period_start'], 'invoices_subscription_period_unique');
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40); // status | invoice | reminder | renewal | suspension | reactivation | note | price
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['subscription_id', 'created_at']);
        });

        Schema::create('subscription_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 30); // before:7 | before:3 | due | overdue:1 | suspension_warning
            $table->date('due_date');
            $table->string('channel', 12)->default('mail');
            $table->string('status', 12)->default('pending'); // pending | sent | failed | skipped
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error', 300)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['subscription_id', 'kind', 'due_date'], 'subscription_reminders_once');
        });

        Schema::create('billing_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // suspend | reactivate
            $table->string('status', 20)->default('open'); // open | done | cancelled | failed
            $table->string('open_key', 80)->nullable()->unique(); // "sub:{id}:{type}" while open → one open task per type
            $table->string('reason', 300)->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_actions');
        Schema::dropIfExists('subscription_reminders');
        Schema::dropIfExists('subscription_events');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_subscription_period_unique');
            $table->dropColumn(['billing_interval', 'period_start', 'period_end']);
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['status', 'next_due_at']);
            $table->dropColumn(['anchor_day', 'price_minor', 'discount_percent', 'currency', 'tax_class', 'grace_ends_at', 'suspended_at', 'cancelled_at', 'expired_at', 'suspension_exempt', 'last_processed_at']);
        });
        Schema::table('plan_prices', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'setup_fee_minor', 'approval_status']);
        });
    }
};
