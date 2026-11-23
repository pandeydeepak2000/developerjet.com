<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renewal lifecycle gaps (completion sprint, milestone 7). Additive only. No refund or proration rule is encoded
 * here: the policies are settings an authorised admin chooses; defaults are the most conservative behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Agreed add-on prices per subscription (snapshot), re-billed on each renewal while active.
        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug', 120);
            $table->string('name', 160);
            $table->unsignedBigInteger('price_minor'); // agreed recurring price per billing period (pre-tax)
            $table->string('status', 20)->default('active'); // active | cancelled
            $table->timestamp('starts_at')->nullable(); // first period it is billed from (next renewal when added mid-cycle)
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['subscription_id', 'status']);
        });

        Schema::create('cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('end_of_period'); // end_of_period | immediate
            $table->string('reason', 1000)->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | withdrawn | completed
            $table->string('open_key', 60)->nullable()->unique(); // "sub:{id}" while pending/approved → one live request
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancel_at')->nullable();
            $table->unsignedSmallInteger('renewal_failures')->default(0);
            $table->string('last_renewal_error', 300)->nullable();
            $table->timestamp('last_renewal_failed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn(['cancel_at_period_end', 'cancel_at', 'renewal_failures', 'last_renewal_error', 'last_renewal_failed_at']));
        Schema::dropIfExists('cancellation_requests');
        Schema::dropIfExists('subscription_addons');
    }
};
