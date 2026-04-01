<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Resolve the current tenant from the request host and bind it
     * to the service container so it is accessible anywhere via app('tenant').
     *
     * Resolution order:
     *   1. X-Tenant-Slug header  (useful for API clients / Postman)
     *   2. Subdomain             (academy-name.yourdomain.com)
     *   3. Full custom domain    (www.academy-name.com)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if (! $tenant) {
            return response()->json([
                'message' => 'Academy not found or inactive.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Bind to container — accessible globally
        App::instance('tenant',    $tenant);
        App::instance('tenant_id', $tenant->id);

        // Make timezone available for date formatting across the app
        config(['app.timezone' => $tenant->timezone]);
        date_default_timezone_set($tenant->timezone);

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $cacheSeconds = 300; // 5 min — tenants change rarely

        // 1. Header-based (dev/API use)
        if ($slug = $request->header('X-Tenant-Slug')) {
            return Cache::remember("tenant:slug:{$slug}", $cacheSeconds, fn () =>
                Tenant::where('slug', $slug)->where('is_active', true)->first()
            );
        }

        $host = $request->getHost();

        // 2. Subdomain-based
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            $slug = $parts[0];
            return Cache::remember("tenant:slug:{$slug}", $cacheSeconds, fn () =>
                Tenant::where('slug', $slug)->where('is_active', true)->first()
            );
        }

        // 3. Full custom domain
        return Cache::remember("tenant:domain:{$host}", $cacheSeconds, fn () =>
            Tenant::where('domain', $host)->where('is_active', true)->first()
        );
    }
}
