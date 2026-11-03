<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless(
            $user && $user->is_active && $user->is_admin
                && ($user->admin_role === null || $user->admin_role === '' || $user->admin_role === 'super_admin'),
            403
        );

        return $next($request);
    }
}
