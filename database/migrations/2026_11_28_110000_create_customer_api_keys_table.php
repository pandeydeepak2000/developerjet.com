<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer AI provider keys saved once on the account and reused by services (additive).
 * The key itself is encrypted (model cast) and never displayed again; only the last 4 characters are kept
 * in clear text so the customer can recognise it ("sk-…a1b2").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('label', 80);
            $table->text('secret');
            $table->string('last4', 4);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_api_keys');
    }
};
