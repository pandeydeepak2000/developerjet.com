<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support workflow (completion sprint, milestone 5). Additive: SLA targets are NULL (= no SLA) until an
 * authorised admin configures them; nothing is invented.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('description', 255)->nullable();
            $table->string('alert_email', 190)->nullable(); // optional team inbox for new-ticket alerts
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sla_first_response_hours')->nullable();
            $table->unsignedSmallInteger('sla_resolution_hours')->nullable();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('sla_breached_at')->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->index(['status', 'priority']);
        });

        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'priority']);
            $table->dropColumn(['first_response_at', 'first_response_due_at', 'resolution_due_at', 'sla_breached_at', 'escalation_level', 'escalated_at', 'closed_at']);
        });
        Schema::table('departments', fn (Blueprint $table) => $table->dropColumn(['description', 'alert_email', 'is_active', 'sla_first_response_hours', 'sla_resolution_hours']));
    }
};
