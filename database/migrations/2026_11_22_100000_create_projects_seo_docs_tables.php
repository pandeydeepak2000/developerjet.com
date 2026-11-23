<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer requirement submissions (WordPress / Laravel / web / SEO) that lead to a quote.
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 30);
            $table->json('requirements');
            $table->string('status', 20)->default('submitted'); // submitted | quoted | accepted | declined | converted
            $table->unsignedBigInteger('quote_minor')->nullable(); // set by staff only; never auto-charged
            $table->text('quote_note')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 30);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('status', 20)->default('planning'); // planning | active | on_hold | review | completed | cancelled
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('pending'); // pending | in_progress | awaiting_approval | approved | revision_requested
            $table->date('due_date')->nullable();
            $table->timestamp('approval_requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('client_feedback')->nullable();
            $table->timestamps();
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('status', 15)->default('todo'); // todo | doing | done
            $table->timestamps();
        });

        Schema::create('project_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_staff')->default(false);
            $table->string('type', 20)->default('message'); // message | feedback | revision | history
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('seo_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('website_url');
            $table->string('type', 30)->default('technical'); // technical | meta_tags | sitemap
            $table->string('status', 15)->default('recorded');
            $table->json('findings')->nullable(); // entered by staff or imported from a connected source
            $table->string('source', 30)->default('manual'); // manual | search_console (only when really connected)
            $table->timestamps();
        });

        Schema::create('seo_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('kind', 15)->default('task'); // task | recommendation
            $table->string('status', 15)->default('pending'); // pending | completed | approved | declined
            $table->string('period', 7)->nullable(); // YYYY-MM
            $table->timestamps();
        });

        Schema::create('seo_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('file_path')->nullable(); // staff-uploaded PDF on the private disk
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('doc_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('doc_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_category_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('meta_description', 200)->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_articles');
        Schema::dropIfExists('doc_categories');
        Schema::dropIfExists('seo_reports');
        Schema::dropIfExists('seo_tasks');
        Schema::dropIfExists('seo_audits');
        Schema::dropIfExists('project_messages');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('milestones');
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });
        Schema::dropIfExists('projects');
        Schema::dropIfExists('service_requests');
    }
};
