<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CRM\LeadController;
use App\Http\Controllers\Api\V1\Students\StudentController;
use App\Http\Controllers\Api\V1\Courses\CourseController;
use App\Http\Controllers\Api\V1\Finance\InvoiceController;
use App\Http\Controllers\Api\V1\Webhooks\WebhookController;
use App\Http\Controllers\Api\V1\Attendance\AttendanceController;
use App\Http\Controllers\Api\V1\Staff\StaffController;
use App\Http\Controllers\Api\V1\Scheduling\SchedulingController;
use Illuminate\Support\Facades\Route;

Route::post('v1/webhooks/lead-intake/{tenantSlug}', [WebhookController::class, 'leadIntake'])->name('webhooks.lead-intake');

Route::prefix('v1')->middleware(['resolve.tenant'])->group(function () {

    Route::post('auth/login',   [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    Route::middleware(['auth:sanctum'])->group(function () {

        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me',      [AuthController::class, 'me']);

        // Phase 1: CRM
        Route::apiResource('leads', LeadController::class);
        Route::patch('leads/{lead}/status',    [LeadController::class, 'updateStatus']);
        Route::post('leads/{lead}/convert',    [LeadController::class, 'convert']);
        Route::post('leads/{lead}/activities', [LeadController::class, 'addActivity']);

        // Phase 1: Students
        Route::apiResource('students', StudentController::class);
        Route::patch('students/{student}/status', [StudentController::class, 'updateStatus']);
        Route::post('students/{student}/enroll',  [StudentController::class, 'enroll']);
        Route::get('students/{student}/invoices', [StudentController::class, 'invoices']);

        // Phase 1: Courses
        Route::apiResource('courses', CourseController::class);
        Route::get('courses/{course}/students', [CourseController::class, 'students']);

        // Phase 1: Finance
        Route::apiResource('invoices', InvoiceController::class)->except('destroy');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment']);
        Route::patch('invoices/{invoice}/cancel',  [InvoiceController::class, 'cancel']);

        // Phase 2: Attendance
        Route::prefix('attendance')->group(function () {
            Route::get('sessions',                     [AttendanceController::class, 'sessions']);
            Route::post('sessions',                    [AttendanceController::class, 'createSession']);
            Route::get('sessions/{session}',           [AttendanceController::class, 'showSession']);
            Route::post('sessions/{session}/check-in', [AttendanceController::class, 'checkIn']);
            Route::post('sessions/{session}/bulk',     [AttendanceController::class, 'bulkUpdate']);
            Route::get('students/{studentId}/report',  [AttendanceController::class, 'studentReport']);
        });

        // Phase 2: Staff
        Route::prefix('staff')->group(function () {
            Route::get('',             [StaffController::class, 'index']);
            Route::post('',            [StaffController::class, 'store']);
            Route::get('summary',      [StaffController::class, 'summary']);
            Route::get('{profile}',    [StaffController::class, 'show']);
            Route::patch('{profile}',  [StaffController::class, 'update']);
            Route::get('leave',                   [StaffController::class, 'leaveIndex']);
            Route::post('leave',                  [StaffController::class, 'requestLeave']);
            Route::patch('leave/{leave}/approve', [StaffController::class, 'approveLeave']);
            Route::patch('leave/{leave}/reject',  [StaffController::class, 'rejectLeave']);
            Route::post('salary/generate',        [StaffController::class, 'generateSalary']);
            Route::patch('salary/{payment}/pay',  [StaffController::class, 'recordSalaryPayment']);
        });

        // Phase 2: Scheduling
        Route::prefix('scheduling')->group(function () {
            Route::get('events',                           [SchedulingController::class, 'events']);
            Route::post('events',                          [SchedulingController::class, 'createEvent']);
            Route::patch('events/{event}',                 [SchedulingController::class, 'updateEvent']);
            Route::delete('events/{event}',                [SchedulingController::class, 'deleteEvent']);
            Route::post('events/{event}/push-to-calendar', [SchedulingController::class, 'pushToCalendar']);
            Route::get('calendars',                        [SchedulingController::class, 'calendars']);
            Route::post('calendars',                       [SchedulingController::class, 'createCalendar']);
            Route::post('calendars/{account}/sync',        [SchedulingController::class, 'syncCalendar']);
        });

    });
});
