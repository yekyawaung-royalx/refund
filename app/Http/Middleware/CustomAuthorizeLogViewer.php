<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CustomAuthorizeLogViewer
{
    // allowed emails
    protected $allowedEmails = [
        'yekyawaung@royalx.net',
    ];

    public function handle(Request $request, Closure $next)
    {
        // login check
        if (!auth()->check()) {
            abort(403, 'Unauthorized');
        }

        // email check
        if (!in_array(auth()->user()->email, $this->allowedEmails)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}