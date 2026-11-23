<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'paid_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['user_id', 'last_activity_at']);
            $table->index(['status', 'last_activity_at']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'created_at']);
            $table->index(['role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'paid_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['status', 'created_at']);
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'last_activity_at']);
            $table->dropIndex(['status', 'last_activity_at']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['status', 'created_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'created_at']);
            $table->dropIndex(['role', 'is_active']);
        });
    }
};
