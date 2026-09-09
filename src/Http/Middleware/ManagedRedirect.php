<?php

namespace SocraNext\Statamic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SocraNext\Statamic\Support\StateStore;
use Statamic\Facades\{Data, Site};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Only recover missing public URLs recorded by an explicit connector rename. */
class ManagedRedirect
{
    public function __construct(private StateStore $store) {}

    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || $this->reserved($request->path())) return $next($request);
        try { $response = $next($request); }
        catch (NotFoundHttpException $exception) {
            if ($redirect = $this->redirect($request)) return $redirect;
            throw $exception;
        }
        // Valid native pages, protection responses and other redirects always win.
        return $response->getStatusCode() === 404 ? ($this->redirect($request) ?? $response) : $response;
    }

    private function redirect(Request $request)
    {
        $source = $request->url();
        $origin = $this->origin($source);
        $base = $this->origin((string) config('socranext.site_url', config('app.url')));
        // Relative Statamic site URLs inherit our configured origin, never an untrusted Host header.
        $allowed = Site::all()->map(fn ($site) => $this->origin($site->url()) ?? $base)->all();
        if ($origin === null || ! in_array($origin, $allowed, true)) return null;
        $redirects = $this->store->get('redirects', []);
        $target = $redirects[$source] ?? null;
        $visited = [$source => true];
        for ($hop = 0; $hop < 20 && is_string($target); $hop++) {
            if (isset($visited[$target]) || $this->origin($target) !== $origin) return null;
            $parts = parse_url($target);
            if (isset($parts['query']) || isset($parts['fragment']) || $this->reserved(ltrim($parts['path'] ?? '', '/'))) return null;
            $visited[$target] = true;
            $resource = Data::findByRequestUrl($target);
            if ($resource && method_exists($resource, 'private') && ! $resource->private()
                && (! method_exists($resource, 'status') || $resource->status() === 'published') && ! $resource->get('redirect')) {
                return redirect()->to($target, 301)->withHeaders(['Cache-Control' => 'no-cache']);
            }
            $target = $redirects[$target] ?? null;
        }
        return null;
    }

    private function origin(string $url): ?string
    {
        if (preg_match('/[\x00-\x20\\\\]/', $url)) return null;
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) return null;
        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) return null;
        return $scheme.'://'.strtolower($parts['host']).':'.($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    }

    private function reserved(string $path): bool
    {
        foreach (['api', 'socranext', trim((string) config('statamic.cp.route', 'cp'), '/'), trim((string) config('statamic.routes.action', '!'), '/')] as $prefix) {
            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'))) return true;
        }
        return false;
    }
}
