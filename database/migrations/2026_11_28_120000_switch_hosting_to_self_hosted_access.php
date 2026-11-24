<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner decision (2026-11-24): every hosting product is self-hosted — the customer gets the VPS with full root
 * access and is responsible for it afterwards. Existing plans and servers move from "managed" to "unmanaged"
 * (the internal value for root-access handover), new rows default to it, and the old "… Managed" category names
 * shown on the site are renamed. Nothing is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')->where('kind', 'hosting')->where('access_model', 'managed')->update(['access_model' => 'unmanaged']);
        DB::table('servers')->where('access_model', 'managed')->update(['access_model' => 'unmanaged']);

        DB::table('product_categories')->where('slug', 'n8n')->where('name', 'n8n Managed')
            ->update(['name' => 'n8n Hosting', 'description' => 'Self-hosted n8n installed on your own VPS.']);
        DB::table('product_categories')->where('slug', 'openclaw')->where('name', 'OpenClaw Managed Hosting')
            ->update(['name' => 'OpenClaw Hosting', 'description' => 'OpenClaw installed on your own VPS.']);

        // Seeded descriptions / taglines / features that still say "managed" (only exact seeded wording is touched;
        // anything an admin rewrote is left alone).
        $categoryText = [
            'claude-code' => ['A managed VPS prepared for running Claude Code with your own eligible Claude subscription or API credentials.', 'A VPS of your own prepared for running Claude Code with your own eligible Claude subscription or API credentials.'],
            'docker-vps' => ['An unmanaged developer VPS with Docker Engine and Compose for your own containers.', 'A developer VPS with Docker Engine and Compose and full root access.'],
        ];
        foreach ($categoryText as $slug => [$old, $new]) {
            DB::table('product_categories')->where('slug', $slug)->where('description', $old)->update(['description' => $new]);
        }
        foreach (DB::table('product_categories')->where('description', 'like', '% delivered as managed projects.')->get() as $cat) {
            DB::table('product_categories')->where('id', $cat->id)->update(['description' => str_replace(' delivered as managed projects.', ' delivered as projects.', $cat->description)]);
        }
        DB::table('plans')->where('tagline', 'Hermes CLI, Hermes Workspace and n8n on one powerful managed server')
            ->update(['tagline' => 'Hermes CLI, Hermes Workspace and n8n on one powerful server of your own']);
        DB::table('plans')->where('tagline', 'Hermes CLI, Hermes Workspace and n8n together on a planned managed configuration.')
            ->update(['tagline' => 'Hermes CLI, Hermes Workspace and n8n together on one server of your own.']);
        foreach (DB::table('plans')->where('features', 'like', '%managed VPS%')->get(['id', 'features']) as $plan) {
            $features = str_replace(['n8n installed on a managed VPS', 'Backups and updates'], ['n8n installed on your own VPS', 'Full root access'], (string) $plan->features);
            DB::table('plans')->where('id', $plan->id)->update(['features' => $features]);
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->string('access_model', 12)->default('unmanaged')->change();
        });
        Schema::table('servers', function (Blueprint $table) {
            $table->string('access_model', 12)->default('unmanaged')->change();
        });
    }

    public function down(): void
    {
        // Data change only; the previous access model is not restored automatically.
    }
};
