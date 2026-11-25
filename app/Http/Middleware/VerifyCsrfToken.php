<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    /**
     * Do not send the XSRF-TOKEN cookie. It exists for JS clients (axios)
     * that read it and echo it back as a header; this app never does —
     * forms post the _token field and every fetch() is a GET. The cookie
     * cannot be HttpOnly by design, which the security scan flagged
     * (QID 150123), so the fix is to stop issuing it.
     */
    protected $addHttpCookie = false;

    protected $except = [
        'login',
        'logout',
    ];
}
