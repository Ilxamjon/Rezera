<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class OptionalSanctumAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() && $request->user() === null) {
            $user = Auth::guard('sanctum')->user();

            if ($user !== null) {
                Auth::setUser($user);
            }
        }

        return $next($request);
    }
}
