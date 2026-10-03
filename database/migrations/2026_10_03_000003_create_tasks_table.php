<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('round')->default(0);   // 0 = draft, 1.. = published rounds

            $table->string('title_fa');
            $table->string('title_en');
            $table->text('description_fa')->nullable();
            $table->text('description_en')->nullable();
            $table->string('priority', 10)->default('medium'); // high | medium | low
            $table->string('source', 10)->default('manual');   // manual | rule | ai
            $table->text('reason')->nullable();                // admin-only: why it was suggested
            $table->json('evidence')->nullable();              // admin-only: data point behind a rule

            // draft -> todo -> submitted | not_done -> approved | rejected | dropped
            $table->string('status', 15)->default('todo')->index();
            $table->text('client_note')->nullable();
            $table->text('admin_feedback')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
