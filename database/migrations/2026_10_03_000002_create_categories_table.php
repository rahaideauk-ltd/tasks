<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Categories are dynamic: created by the AI / rules / admin per project.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name_fa');
            $table->string('name_en');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['project_id', 'name_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
