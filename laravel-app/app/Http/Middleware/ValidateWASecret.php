<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWASecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->input('secret') ?? $request->header('X-WA-Secret');
        $expected = config('services.wa.secret');

        if (! $incoming || ! $expected || ! hash_equals($expected, $incoming)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
