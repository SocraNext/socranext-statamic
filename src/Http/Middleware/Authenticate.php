<?php

namespace SocraNext\Statamic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SocraNext\Statamic\Support\Connection;

class Authenticate
{
    public function __construct(private Connection $connection) {}

    public function handle(Request $request, Closure $next)
    {
        $header = (string) $request->header('x-socranext-token', '');
        $bearer = (string) $request->bearerToken();
        if (($header && $bearer && !hash_equals($header, $bearer)) || !$this->connection->accepts($header ?: $bearer)) {
            return response()->json(['success' => false, 'code' => 'unauthorized', 'message' => 'Reconnect SocraNext from the control panel.'], 401);
        }
        return $next($request);
    }
}
