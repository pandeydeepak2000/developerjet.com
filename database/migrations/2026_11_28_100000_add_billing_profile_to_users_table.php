<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved billing details on the customer account (additive). Checkout no longer asks for an address every time:
 * invoices use these values, which the customer manages once under Profile → Billing details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('billing_company', 150)->nullable()->after('phone');
            $table->string('gstin', 15)->nullable()->after('billing_company');
            $table->text('billing_address')->nullable()->after('gstin');
            $table->string('billing_city', 80)->nullable()->after('billing_address');
            $table->string('billing_state', 80)->nullable()->after('billing_city');
            $table->string('billing_postcode', 12)->nullable()->after('billing_state');
            $table->string('billing_country', 2)->nullable()->after('billing_postcode');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['billing_company', 'gstin', 'billing_address', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country']);
        });
    }
};
