<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Staff profiles ────────────────────────────────────────
        // Extends users with HR-specific data
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('position')->nullable();        // "English Coach", "Admin Manager"
            $table->string('department')->nullable();      // "Academic", "Sales", "Admin"
            $table->string('employment_type')->default('full_time'); // full_time|part_time|contractor
            $table->decimal('salary', 10, 2)->nullable();
            $table->string('salary_period')->default('monthly'); // monthly|weekly|hourly
            $table->string('currency', 3)->default('GTQ');
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('nit')->nullable();
            $table->string('igss_number')->nullable();    // Guatemala social security
            $table->string('bank_account')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'user_id']);
            $table->index('tenant_id');
        });

        // ── PTO / Leave requests ──────────────────────────────────
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');  // vacation|sick|personal|unpaid|other
            $table->date('starts_on');
            $table->date('ends_on');
            $table->integer('days')->default(1);
            $table->string('status')->default('pending'); // pending|approved|rejected|cancelled
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index('status');
        });

        // ── Salary payments ───────────────────────────────────────
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('base_amount', 10, 2);
            $table->decimal('bonuses', 10, 2)->default(0);
            $table->decimal('deductions', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            $table->string('currency', 3)->default('GTQ');
            $table->string('period');              // "2025-03" (year-month)
            $table->string('status')->default('pending'); // pending|paid
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index('period');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('staff_profiles');
    }
};
