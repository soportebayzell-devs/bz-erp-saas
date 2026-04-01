<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -------------------------------------------------------
        // Lead Activities
        // -------------------------------------------------------
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // call|email|note|whatsapp|status_change|converted
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // flexible: {from, to, duration, etc.}
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('lead_id');
            $table->index('type');
        });

        // -------------------------------------------------------
        // Students
        // -------------------------------------------------------
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('nit')->nullable();                   // Guatemala tax ID
            $table->string('status')->default('active');         // active|suspended|graduated|dropped
            $table->string('avatar_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('graduated_at')->nullable();
            $table->timestamp('dropped_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
            $table->index('tenant_id');
            $table->index('status');
            $table->index('lead_id');
        });

        // -------------------------------------------------------
        // Courses
        // -------------------------------------------------------
        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('type'); // online|in_person|hybrid
            $table->string('level')->nullable(); // beginner|intermediate|advanced
            $table->text('description')->nullable();
            $table->integer('capacity')->default(20);
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('GTQ');
            $table->string('status')->default('active'); // draft|active|archived
            $table->string('schedule_description')->nullable(); // "Mon/Wed 6-8pm"
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('type');
        });

        // -------------------------------------------------------
        // Enrollments
        // -------------------------------------------------------
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active'); // active|completed|dropped|transferred
            $table->timestamp('enrolled_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dropped_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'course_id']); // prevent double enrollment
            $table->index('student_id');
            $table->index('course_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('students');
        Schema::dropIfExists('lead_activities');
    }
};
