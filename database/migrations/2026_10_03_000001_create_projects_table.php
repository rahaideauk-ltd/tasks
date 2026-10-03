<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();          // secret link for the client portal
            $table->string('name');
            $table->string('site_url')->nullable();
            $table->text('description')->nullable();
            $table->string('industry')->nullable();
            $table->text('goals')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('locale', 5)->default('fa');

            // integrations (secrets are encrypted via model casts)
            $table->text('google_tokens')->nullable();
            $table->string('gsc_site_url')->nullable();
            $table->string('ga4_property')->nullable();
            $table->text('clarity_token')->nullable();

            // latest data snapshot + latest AI analysis
            $table->json('data')->nullable();
            $table->json('analysis')->nullable();
            $table->timestamp('data_fetched_at')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->string('analysis_status', 20)->default('idle'); // idle | running | done | failed

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
