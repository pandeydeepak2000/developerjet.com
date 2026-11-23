<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 60)->unique();
            $table->string('kind', 30)->default('vps_supplier'); // vps_supplier | broker | dedicated_provider | proxmox_internal
            $table->string('availability', 20)->default('unknown'); // available | limited | unavailable | unknown
            $table->text('regions')->nullable(); // free text, comma separated
            $table->string('provisioning_method', 20)->default('manual'); // manual | api | proxmox
            $table->string('api_status', 20)->default('unverified'); // none | unverified | verified
            $table->json('verified_capabilities')->nullable(); // only capabilities proven against official docs/credentials
            $table->string('supplier_reference')->nullable(); // account / reseller reference — never a credential
            $table->string('website_url')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('provider_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('region')->nullable();
            $table->unsignedSmallInteger('vcpu')->nullable();
            $table->unsignedSmallInteger('ram_gb')->nullable();
            $table->unsignedInteger('disk_gb')->nullable();
            $table->string('billing_period', 12)->default('monthly'); // monthly | quarterly | yearly
            $table->unsignedBigInteger('purchase_cost_minor')->nullable(); // what DeveloperJet pays (internal only)
            $table->unsignedBigInteger('selling_price_minor')->nullable(); // reference selling price; owner-approved prices live on plans
            $table->string('status', 12)->default('draft'); // draft | approved
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->string('supplier_reference')->nullable();
            $table->unsignedBigInteger('purchase_cost_minor')->nullable();
            $table->string('billing_period', 12)->default('monthly');
            $table->date('renewal_date')->nullable();
            $table->string('architecture', 12)->default('x86_64');
            $table->string('access_model', 12)->default('managed'); // managed | unmanaged
            $table->timestamp('suspension_requested_at')->nullable();
            $table->timestamp('renewal_alerted_at')->nullable();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->string('access_model', 12)->default('managed'); // managed | unmanaged
        });

        Schema::table('deployments', function (Blueprint $table) {
            $table->json('completed_steps')->nullable(); // idempotent retry: steps already confirmed on the server
            $table->string('failed_step', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deployments', fn (Blueprint $t) => $t->dropColumn(['completed_steps', 'failed_step']));
        Schema::table('plans', fn (Blueprint $t) => $t->dropColumn('access_model'));
        Schema::table('servers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provider_id');
            $table->dropColumn(['supplier_reference', 'purchase_cost_minor', 'billing_period', 'renewal_date', 'architecture', 'access_model', 'suspension_requested_at', 'renewal_alerted_at']);
        });
        Schema::dropIfExists('provider_offers');
        Schema::dropIfExists('providers');
    }
};
