<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_pools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind', 20)->default('dedicated_vps'); // dedicated_vps | shared_host (never mislabelled)
            $table->string('provider_driver', 30)->nullable(); // future automatic mode; null = manual only
            $table->unsignedSmallInteger('max_overcommit_percent')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_pool_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('ip_address', 45);
            $table->string('provider')->nullable();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->string('ssh_user', 40)->default('root');
            $table->string('role', 30)->default('customer_vps'); // customer_vps | shared | internal
            $table->unsignedSmallInteger('cpu_capacity')->default(0);
            $table->unsignedSmallInteger('ram_capacity_gb')->default(0);
            $table->unsignedSmallInteger('storage_capacity_gb')->default(0);
            $table->string('os_name', 40)->nullable();
            $table->text('ssh_credential')->nullable(); // Laravel-encrypted private key / temp credential; never plaintext, never displayed
            $table->string('ssh_host_fingerprint')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('status', 20)->default('available'); // available | assigned | maintenance | retired
            $table->string('deployment_status', 20)->default('none'); // none | queued | running | succeeded | failed
            $table->timestamps();
            $table->unique(['ip_address', 'ssh_port']);
        });

        Schema::create('server_health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('check', 30);
            $table->string('status', 10); // ok | warn | fail | simulated
            $table->string('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('server_id')->nullable()->constrained('servers')->nullOnDelete();
            $table->string('kind', 30)->default('hermes_cli');
            $table->string('url')->nullable();
            $table->string('custom_domain')->nullable();
            $table->boolean('domain_verified')->default(false);
            $table->string('ai_provider', 30)->nullable();
            $table->text('ai_api_key')->nullable(); // encrypted, write-only
            $table->json('config')->nullable();
        });

        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipe', 40);
            $table->string('recipe_version', 20);
            $table->string('mode', 10)->default('dry_run'); // dry_run | live
            $table->string('status', 20)->default('queued'); // queued | preflight | running | succeeded | failed | cancelled
            $table->string('idempotency_key', 80)->unique();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('error_summary')->nullable(); // safe, customer-neutral message
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deployment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')->constrained()->cascadeOnDelete();
            $table->string('level', 10)->default('info');
            $table->string('step', 40);
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_logs');
        Schema::dropIfExists('deployments');
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('server_id');
            $table->dropColumn(['kind', 'url', 'custom_domain', 'domain_verified', 'ai_provider', 'ai_api_key', 'config']);
        });
        Schema::dropIfExists('server_health_checks');
        Schema::dropIfExists('servers');
        Schema::dropIfExists('server_pools');
    }
};
