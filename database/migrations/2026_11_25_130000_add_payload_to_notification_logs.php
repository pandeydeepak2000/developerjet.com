<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Keeps the (encrypted) alert text so a failed Telegram delivery can be retried from the admin. Additive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->text('payload')->nullable(); // Laravel-encrypted; never contains secrets (bot token is not part of it)
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', fn (Blueprint $table) => $table->dropColumn('payload'));
    }
};
