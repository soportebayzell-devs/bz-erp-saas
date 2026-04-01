<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();           // academy-name → subdomain
            $table->string('domain')->nullable()->unique(); // custom domain support
            $table->string('timezone')->default('America/Guatemala');
            $table->string('currency', 3)->default('GTQ');
            $table->string('logo_url')->nullable();
            $table->string('primary_color', 7)->default('#2563EB');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();       // feature flags, config overrides
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('domain');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
