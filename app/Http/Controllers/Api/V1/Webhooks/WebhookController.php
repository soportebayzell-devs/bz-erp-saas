<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class WebhookController extends Controller
{
    public function __construct(
        private readonly LeadService $leadService
    ) {}

    /**
     * POST /api/v1/webhooks/lead-intake/{tenantSlug}
     *
     * This endpoint is PUBLIC (no auth required) — protected by:
     *   1. Rate limiting (20 req / min per IP)
     *   2. Optional HMAC signature verification
     *   3. Tenant-level webhook secret
     *
     * Payload (flexible — maps common field names):
     * {
     *   "first_name": "Juan",
     *   "last_name":  "García",
     *   "email":      "juan@example.com",
     *   "phone":      "+502 5555-1234",
     *   "source":     "website_form",
     *   "notes":      "Interested in online course",
     *   "course_type": "online",
     *   "_secret": "optional-hmac-signature"  // if tenant has webhook_secret configured
     * }
     */
    public function leadIntake(Request $request, string $tenantSlug): JsonResponse
    {
        // 1. Rate limit per IP
        $key = "webhook:lead-intake:{$request->ip()}";
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return response()->json(['message' => 'Too many requests.'], 429);
        }
        RateLimiter::hit($key, 60);

        // 2. Resolve tenant from slug
        $tenant = Tenant::where('slug', $tenantSlug)
                        ->where('is_active', true)
                        ->first();

        if (! $tenant) {
            return response()->json(['message' => 'Academy not found.'], 404);
        }

        // 3. Verify webhook secret if tenant has one configured
        $webhookSecret = data_get($tenant->settings, 'webhook_secret');
        if ($webhookSecret) {
            $signature = $request->header('X-Webhook-Signature');
            $expected  = hash_hmac('sha256', $request->getContent(), $webhookSecret);

            if (! hash_equals($expected, (string) $signature)) {
                Log::warning('Webhook signature mismatch', [
                    'tenant' => $tenantSlug,
                    'ip'     => $request->ip(),
                ]);
                return response()->json(['message' => 'Invalid signature.'], 401);
            }
        }

        // 4. Bind tenant context for the service layer
        app()->instance('tenant_id', $tenant->id);

        // 5. Normalize payload — handle different field naming conventions
        $data = $request->all();
        $payload = $this->normalizePayload($data, $tenant);

        // 6. Validate minimum required fields
        if (empty($payload['first_name']) && empty($payload['last_name']) && empty($payload['email'])) {
            return response()->json([
                'message' => 'At least one of: first_name, last_name, or email is required.',
            ], 422);
        }

        // 7. Create the lead
        try {
            $lead = $this->leadService->create($payload);

            Log::info('Lead created via webhook', [
                'tenant' => $tenantSlug,
                'lead'   => $lead->id,
                'source' => $payload['source'],
            ]);

            return response()->json([
                'success' => true,
                'lead_id' => $lead->id,
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Webhook lead creation failed', [
                'tenant' => $tenantSlug,
                'error'  => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to process lead.'], 500);
        }
    }

    /**
     * Normalize various field naming conventions from different form providers.
     * Handles: Gravity Forms, Elementor Forms, Typeform, n8n, custom forms.
     */
    private function normalizePayload(array $data, Tenant $tenant): array
    {
        // Common field aliases
        $firstName = $data['first_name']
            ?? $data['firstName']
            ?? $data['nombre']
            ?? trim(explode(' ', $data['full_name'] ?? $data['name'] ?? '')[0])
            ?? '';

        $lastName = $data['last_name']
            ?? $data['lastName']
            ?? $data['apellido']
            ?? (str_contains($data['full_name'] ?? '', ' ')
                ? substr($data['full_name'], strpos($data['full_name'], ' ') + 1)
                : '')
            ?? '';

        $courseType = $data['course_type']
            ?? $data['courseType']
            ?? $data['modalidad']
            ?? $data['preferred_course_type']
            ?? null;

        return [
            'tenant_id'             => $tenant->id,
            'first_name'            => $firstName,
            'last_name'             => $lastName,
            'email'                 => $data['email'] ?? $data['correo'] ?? null,
            'phone'                 => $data['phone'] ?? $data['telefono'] ?? $data['whatsapp'] ?? null,
            'source'                => $data['source'] ?? $data['utm_source'] ?? 'webhook',
            'notes'                 => $data['notes'] ?? $data['message'] ?? $data['mensaje'] ?? null,
            'preferred_course_type' => in_array($courseType, ['online', 'in_person', 'hybrid'])
                                        ? $courseType : null,
            'interest_level'        => $data['interest_level'] ?? 'medium',
        ];
    }
}
