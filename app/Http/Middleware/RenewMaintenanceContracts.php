<?php

namespace App\Http\Middleware;

use App\Models\MaintenanceContract;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Rolls expired auto-renew contracts into their next term on the first
 * request of each day, so the contracts list, expiry report and dashboard
 * never show one as expired. Done here rather than relying only on the
 * scheduler because nothing guarantees a cron job / Task Scheduler entry
 * exists on the server; the contracts:auto-renew command does the same.
 */
class RenewMaintenanceContracts
{
    public function handle(Request $request, Closure $next)
    {
        // Cache::add only succeeds for the first request of the day.
        if (Cache::add('maintenance-auto-renew:' . now()->toDateString(), true, now()->endOfDay())) {
            try {
                MaintenanceContract::renewExpired();
            } catch (\Throwable $e) {
                // Never take a page down over this; try again on the next request.
                Cache::forget('maintenance-auto-renew:' . now()->toDateString());
                report($e);
            }
        }

        return $next($request);
    }
}
