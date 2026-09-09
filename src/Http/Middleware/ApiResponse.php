<?php

namespace SocraNext\Statamic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ApiResponse
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        if (strlen($request->getContent()) > 8 * 1024 * 1024) return response()->json(['success' => false, 'code' => 'payload_too_large'], 413);
        try {
            $response = $next($request);
        } catch (ValidationException $error) {
            $response = response()->json(['success' => false, 'code' => 'validation_failed', 'message' => $error->getMessage(), 'errors' => $error->errors()], 422);
        } catch (HttpExceptionInterface $error) {
            $response = response()->json(['success' => false, 'code' => 'request_rejected', 'message' => $error->getMessage()], $error->getStatusCode());
        }
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}
