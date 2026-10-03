<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Internal knowledge about a project. Admin-only: never shown on the client portal.
        Schema::table('projects', function (Blueprint $table) {
            $table->text('brief')->nullable()->after('goals');     // written by the admin; Claude reads it, never edits it
            $table->longText('memory')->nullable()->after('brief'); // maintained by Claude after every analysis; admin can edit
            $table->timestamp('memory_updated_at')->nullable()->after('memory');
        });

        // Dynamic fields: keywords, competitors, features... Any `kind` is allowed.
        Schema::create('project_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('value', 255);
            $table->text('note')->nullable();
            $table->string('source', 10)->default('manual');      // manual | ai
            $table->string('status', 10)->default('confirmed');   // suggested | confirmed | rejected
            $table->timestamps();
            $table->unique(['project_id', 'kind', 'value']);
        });

        // Every version of the memory, so an AI rewrite never loses admin edits for good.
        Schema::create('memory_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->longText('body')->nullable();
            $table->string('source', 10);                          // admin | ai
            $table->timestamps();
        });

        // Playbooks Claude follows for a kind of task. project_id null = available to every project.
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('slug', 60);
            $table->string('name');
            $table->text('description')->nullable();               // when to use it
            $table->longText('instructions');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('skill_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('skill_id');
        });
        Schema::dropIfExists('skills');
        Schema::dropIfExists('memory_revisions');
        Schema::dropIfExists('project_facts');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['brief', 'memory', 'memory_updated_at']);
        });
    }
};
