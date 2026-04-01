<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('student_id')->nullable()->constrained('students')->nullOnDelete();

            // Personal info
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Lead metadata
            $table->string('source')->nullable();   // website|referral|whatsapp|api
            $table->string('status')->default('new'); // new|contacted|qualified|converted|lost
            $table->string('interest_level')->nullable(); // low|medium|high
            $table->string('preferred_course_type')->nullable(); // online|in_person|hybrid
            $table->text('notes')->nullable();

            // Conversion tracking
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('lost_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('assigned_to');
            $table->index(['tenant_id', 'status']); // compound: most common query
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
