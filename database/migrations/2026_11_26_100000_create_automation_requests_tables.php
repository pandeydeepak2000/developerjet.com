<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Business Automation leads (guest or customer requirement submissions) and their sales/quotation history.
 * Additive only: two new tables, plus grants for the NEW "leads" permission module on the default role templates
 * (the module did not exist before, so no administrator edit can be overwritten). Down drops only what this adds.
 */
return new class extends Migration
{
    private const GRANTS = [
        // supplier_costs.view keeps the Administrator template's existing access to supplier purchase costs, which
        // are now hidden from roles (e.g. Technical Support) that only hold servers.view.
        'admin' => ['leads.view', 'leads.edit', 'leads.assign', 'leads.export', 'supplier_costs.view'],
        'sales-staff' => ['leads.view', 'leads.edit'],
        'technical-support' => ['leads.view'],
        'developer' => ['leads.view'],
    ];

    public function up(): void
    {
        Schema::create('automation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 20);
            $table->string('company', 150)->nullable();
            $table->string('website', 200)->nullable();
            $table->string('business_type', 120);
            $table->string('business_size', 30)->nullable();
            $table->text('current_tools')->nullable();
            $table->text('integrations')->nullable();
            $table->string('timeline', 30)->nullable();
            $table->string('budget', 30)->nullable();
            $table->text('requirements');
            $table->text('notes')->nullable();
            $table->boolean('redacted')->default(false); // a likely password/API key was removed before storage
            // new | reviewing | assigned | requirements_discussion | quotation_prepared | quotation_sent | accepted | declined | converted | closed
            $table->string('status', 30)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('technical_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('follow_up_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('follow_up_at')->nullable();
            $table->unsignedBigInteger('quote_minor')->nullable(); // proposal only — never charged automatically
            $table->text('quote_summary')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('quote_sent_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_source', 20)->nullable(); // customer_dashboard | recorded_by_staff
            $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('submission_token', 64)->nullable()->unique(); // one record per rendered form (double submit)
            $table->string('fingerprint', 64)->index();                   // email + requirements (repeat submissions)
            $table->string('ip_hash', 64)->nullable();                    // HMAC of the IP, never the raw address
            $table->timestamps();
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('automation_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30); // submitted | status | note | contact | info_request | quote | assignment | follow_up | decision | conversion
            $table->string('visibility', 10)->default('internal'); // internal | customer
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('roles') && Schema::hasTable('role_permissions')) {
            foreach (self::GRANTS as $slug => $permissions) {
                $roleId = DB::table('roles')->where('slug', $slug)->value('id');
                if (! $roleId) {
                    continue;
                }
                foreach ($permissions as $permission) {
                    if (! DB::table('role_permissions')->where('role_id', $roleId)->where('permission', $permission)->exists()) {
                        DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->where('permission', 'like', 'leads.%')->orWhere('permission', 'supplier_costs.view')->delete();
        }
        Schema::dropIfExists('automation_request_events');
        Schema::dropIfExists('automation_requests');
    }
};
