<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CRM\LeadController;
use App\Http\Controllers\Api\V1\Students\StudentController;
use App\Http\Controllers\Api\V1\Courses\CourseController;
use App\Http\Controllers\Api\V1\Finance\InvoiceController;
use App\Http\Controllers\Api\V1\Webhooks\WebhookController;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════════
// WEBHOOKS — public, tenant resolved from URL slug
// Rate-limited in the controller itself
// ═══════════════════════════════════════════════════════════════
Route::post(
    'v1/webhooks/lead-intake/{tenantSlug}',
    [WebhookController::class, 'leadIntake']
)->name('webhooks.lead-intake');


// ═══════════════════════════════════════════════════════════════
// AUTHENTICATED API  — tenant resolved from subdomain / header
// ═══════════════════════════════════════════════════════════════
Route::prefix('v1')
     ->middleware(['resolve.tenant'])
     ->group(function () {

    // ----------------------------------------------------------
    // Auth (public within tenant context)
    // ----------------------------------------------------------
    Route::post('auth/login',   [AuthController::class, 'login'])->name('auth.login');
    Route::post('auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');

    // ----------------------------------------------------------
    // Protected routes
    // ----------------------------------------------------------
    Route::middleware(['auth:sanctum'])->group(function () {

        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me',      [AuthController::class, 'me'])->name('auth.me');

        // ── CRM / Leads ──────────────────────────────────────
        Route::apiResource('leads', LeadController::class);
        Route::patch('leads/{lead}/status',    [LeadController::class, 'updateStatus'])->name('leads.status');
        Route::post('leads/{lead}/convert',    [LeadController::class, 'convert'])->name('leads.convert');
        Route::post('leads/{lead}/activities', [LeadController::class, 'addActivity'])->name('leads.activities.store');

        // ── Students ─────────────────────────────────────────
        Route::apiResource('students', StudentController::class);
        Route::patch('students/{student}/status', [StudentController::class, 'updateStatus'])->name('students.status');
        Route::post('students/{student}/enroll',  [StudentController::class, 'enroll'])->name('students.enroll');
        Route::get('students/{student}/invoices', [StudentController::class, 'invoices'])->name('students.invoices');

        // ── Courses ───────────────────────────────────────────
        Route::apiResource('courses', CourseController::class);
        Route::get('courses/{course}/students', [CourseController::class, 'students'])->name('courses.students');

        // ── Finance ───────────────────────────────────────────
        Route::apiResource('invoices', InvoiceController::class)->except('destroy');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments.store');
        Route::patch('invoices/{invoice}/cancel',  [InvoiceController::class, 'cancel'])->name('invoices.cancel');

    });
});
