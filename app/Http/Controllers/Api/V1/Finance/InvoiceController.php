<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Services\InvoiceService;
use App\Modules\Students\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    // GET /api/v1/invoices
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'     => 'nullable|string',
            'student_id' => 'nullable|uuid',
            'overdue'    => 'nullable|boolean',
            'from'       => 'nullable|date',
            'to'         => 'nullable|date',
            'per_page'   => 'nullable|integer|min:1|max:100',
        ]);

        $invoices = Invoice::query()
            ->with(['student:id,first_name,last_name,email', 'items'])
            ->when($request->status,     fn ($q) => $q->where('status', $request->status))
            ->when($request->student_id, fn ($q) => $q->where('student_id', $request->student_id))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->from, fn ($q) => $q->where('due_date', '>=', $request->from))
            ->when($request->to,   fn ($q) => $q->where('due_date', '<=', $request->to))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 25);

        return response()->json($invoices);
    }

    // POST /api/v1/invoices
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'course_id'  => 'required|uuid|exists:courses,id',
            'due_date'   => 'nullable|date',
            'discount'   => 'nullable|numeric|min:0',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $student = Student::findOrFail($data['student_id']);
        $invoice = $this->invoiceService->generateForEnrollment($student, $data);

        return response()->json($invoice, 201);
    }

    // GET /api/v1/invoices/{invoice}
    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json(
            $invoice->load(['student', 'items', 'payments'])
        );
    }

    // POST /api/v1/invoices/{invoice}/payments
    public function recordPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'method'    => 'required|in:cash,bank_transfer,card,other',
            'reference' => 'nullable|string|max:100',
            'notes'     => 'nullable|string|max:500',
        ]);

        $invoice = $this->invoiceService->recordPayment($invoice, $data);

        return response()->json([
            'message' => 'Payment recorded.',
            'invoice' => $invoice,
        ]);
    }

    // PATCH /api/v1/invoices/{invoice}/cancel
    public function cancel(Request $request, Invoice $invoice): JsonResponse
    {
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return response()->json(['message' => 'Invoice cannot be cancelled.'], 422);
        }

        $invoice->update(['status' => 'cancelled']);

        return response()->json($invoice);
    }
}
