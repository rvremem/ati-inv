<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $userRole = auth()->user()->role;

        if (in_array($userRole, $roles) || $userRole === 'SA') {
            return $next($request);
        }

        abort(403, 'Unauthorized access');
    }
}
