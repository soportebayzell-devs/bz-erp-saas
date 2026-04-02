<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Calendar accounts ─────────────────────────────────────
        // Each tenant can connect one or more Nextcloud/CalDAV accounts
        Schema::create('calendar_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                        // "Academy Main Calendar"
            $table->string('provider')->default('caldav'); // caldav|google (future)
            $table->string('caldav_url');                  // https://nextcloud.com/remote.php/dav/
            $table->string('username');
            $table->text('password');                      // encrypted at app level
            $table->string('calendar_path')->nullable();   // /calendars/user/calendar-name/
            $table->string('color')->default('#2563EB');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });

        // ── Scheduled events ──────────────────────────────────────
        Schema::create('scheduled_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('calendar_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('type')->default('class'); // class|meeting|event|holiday
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_rule')->nullable(); // RRULE:FREQ=WEEKLY;BYDAY=MO,WE
            $table->string('status')->default('scheduled'); // scheduled|cancelled|completed
            // CalDAV sync fields
            $table->string('caldav_uid')->nullable();       // UID from CalDAV server
            $table->string('caldav_etag')->nullable();      // ETag for change detection
            $table->text('caldav_data')->nullable();        // Raw iCal data
            $table->timestamp('caldav_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'starts_at']);
            $table->index('course_id');
            $table->index('coach_id');
            $table->index('caldav_uid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_events');
        Schema::dropIfExists('calendar_accounts');
    }
};
