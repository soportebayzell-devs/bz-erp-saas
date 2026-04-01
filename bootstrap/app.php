<?php

use App\Http\Middleware\ResolveTenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Register the tenant resolver as a named middleware
        $middleware->alias([
            'resolve.tenant' => ResolveTenant::class,
        ]);

        // Throttle API requests globally: 60 req/min
        $middleware->throttleApi(60);
    })
    ->withSchedule(function (Schedule $schedule) {

        // Mark overdue invoices every day at midnight
        $schedule->command('erp:mark-overdue-invoices')
                 ->dailyAt('00:05')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Send follow-up reminders for stale leads, daily at 8am
        $schedule->command('erp:send-followup-reminders --days=7')
                 ->dailyAt('08:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Snapshot Horizon metrics for dashboards
        $schedule->command('horizon:snapshot')
                 ->everyFiveMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // Return JSON for all API errors instead of HTML redirects
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Clean 404 message for API consumers
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Resource not found.',
                ], 404);
            }
        });
    })
    ->create();
