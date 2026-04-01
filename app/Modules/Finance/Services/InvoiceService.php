<?php

namespace App\Modules\Finance\Services;

use App\Modules\Courses\Models\Course;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\InvoiceItem;
use App\Modules\Finance\Models\Payment;
use App\Modules\Students\Models\Student;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    // -----------------------------------------------------------
    // Generate invoice for a course enrollment
    // -----------------------------------------------------------

    /**
     * @param  Student  $student
     * @param  array    $data  {
     *   course_id: string,
     *   due_date?: string,
     *   discount?: float,
     *   notes?: string,
     * }
     */
    public function generateForEnrollment(Student $student, array $data): Invoice
    {
        $course = Course::findOrFail($data['course_id']);

        return DB::transaction(function () use ($student, $course, $data) {

            $subtotal = $course->price;
            $discount = (float) ($data['discount'] ?? 0);
            $tax      = $this->calculateTax($subtotal - $discount, $student->tenant_id);
            $total    = $subtotal - $discount + $tax;

            $invoice = Invoice::create([
                'tenant_id'  => $student->tenant_id,
                'student_id' => $student->id,
                'number'     => $this->generateNumber($student->tenant_id),
                'status'     => 'pending',
                'subtotal'   => $subtotal,
                'tax'        => $tax,
                'discount'   => $discount,
                'total'      => $total,
                'currency'   => $course->currency,
                'due_date'   => $data['due_date'] ?? now()->addDays(7)->toDateString(),
                'notes'      => $data['notes'] ?? null,
            ]);

            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => "Enrollment: {$course->name}",
                'quantity'    => 1,
                'unit_price'  => $course->price,
                'total'       => $course->price,
            ]);

            return $invoice->load('items', 'student');
        });
    }

    // -----------------------------------------------------------
    // Record a payment against an invoice
    // -----------------------------------------------------------

    /**
     * @param  Invoice  $invoice
     * @param  array    $data  {
     *   amount: float,
     *   method: string,       // cash|bank_transfer|card|other
     *   reference?: string,
     *   notes?: string,
     * }
     */
    public function recordPayment(Invoice $invoice, array $data): Invoice
    {
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            throw new \LogicException("Cannot record payment on a [{$invoice->status}] invoice.");
        }

        DB::transaction(function () use ($invoice, $data) {

            Payment::create([
                'invoice_id'  => $invoice->id,
                'amount'      => $data['amount'],
                'method'      => $data['method'],
                'status'      => 'completed',
                'reference'   => $data['reference'] ?? null,
                'notes'       => $data['notes'] ?? null,
                'recorded_by' => auth()->user()?->name,
                'paid_at'     => now(),
            ]);

            $totalPaid = $invoice->payments()
                ->where('status', 'completed')
                ->sum('amount');

            $isPaidInFull = $totalPaid >= $invoice->total;

            $invoice->update([
                'status'  => $isPaidInFull ? 'paid' : 'partial',
                'paid_at' => $isPaidInFull ? now() : null,
            ]);
        });

        return $invoice->fresh(['payments', 'items', 'student']);
    }

    // -----------------------------------------------------------
    // Mark overdue invoices (called by scheduled command)
    // -----------------------------------------------------------

    public function markOverdueInvoices(): int
    {
        return Invoice::query()
            ->whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    // -----------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------

    private function generateNumber(string $tenantId): string
    {
        $prefix = config('erp.invoice_prefix', 'INV');
        $year   = now()->format('Y');

        $count = Invoice::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('%s-%s-%05d', $prefix, $year, $count);
    }

    private function calculateTax(float $amount, string $tenantId): float
    {
        // Default: Guatemala IVA 12%
        // Tenant-level override possible via settings JSON
        $taxRate = config('erp.default_tax_rate', 0.12);

        return round($amount * $taxRate, 2);
    }
}
