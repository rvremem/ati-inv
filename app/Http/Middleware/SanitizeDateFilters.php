<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Drops any date filter on a report request that is not a plain Y-m-d date.
 *
 * The report pages echo their filters back into the page (form inputs and the
 * DataTables bootstrap script), so a crafted value like
 * ?as_of_date="><img src=x> was reflected — the scan's XSS finding. The views
 * escape it now too; this makes sure nothing but a real date reaches the
 * controllers, the queries or the page at all. A bad value is treated as if
 * the filter was never set rather than raising a validation error, since
 * these are GET filters bookmarked in links.
 */
class SanitizeDateFilters
{
    private const DATE_KEYS = ['as_of_date', 'from_date', 'to_date', 'date'];

    public function handle(Request $request, Closure $next)
    {
        foreach (self::DATE_KEYS as $key) {
            if (!$request->query->has($key)) {
                continue;
            }

            $value = $request->query->get($key);
            if ($value === null || $value === '' || (is_string($value) && self::isDate($value))) {
                continue;
            }

            $request->query->remove($key);
            // input() reads the merged bag, so clear it there as well.
            $request->request->remove($key);
        }

        return $next($request);
    }

    private static function isDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y);
    }
}
