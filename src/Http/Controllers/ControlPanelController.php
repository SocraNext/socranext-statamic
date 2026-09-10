<?php

namespace SocraNext\Statamic\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use SocraNext\Statamic\ServiceProvider;
use SocraNext\Statamic\Support\Connection;
use SocraNext\Statamic\Support\StateStore;
use SocraNext\Statamic\Support\Readiness;
use SocraNext\Statamic\Support\Setup;
use SocraNext\Statamic\Content\ContentRepository;
use Statamic\Facades\Site;
use function Statamic\trans as __;

class ControlPanelController
{
    public function index(Connection $connection, StateStore $store, Readiness $readiness, ContentRepository $content)
    {
        try { $sites = $content->sites(); } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { $sites = []; }
        $siteUrl = trim((string) config('socranext.site_url'));
        $https = parse_url($siteUrl, PHP_URL_SCHEME) === 'https' && (bool) parse_url($siteUrl, PHP_URL_HOST);
        $checks = $readiness->checks();
        return response()->view('socranext::cp.index', [
            'connected' => $connection->connected(), 'ready' => $readiness->ready(),
            'automatic' => $readiness->automatic(),
            'frontendChecked' => (bool) (config('socranext.frontend_ready') || $store->get('frontend_ready', false)),
            'version' => ServiceProvider::VERSION, 'setupChecks' => $checks,
            'setupComplete' => !in_array(false, $checks, true),
            'websiteUrl' => $https ? $siteUrl : null,
            'websiteLabel' => parse_url($siteUrl, PHP_URL_HOST) ?: __('socranext::cp.no_website'),
            'siteNames' => array_map(fn ($handle) => Site::get($handle)->name(), $sites),
        ]);
    }

    public function connect(Request $request, Connection $connection, Setup $setup, Readiness $readiness)
    {
        $endpoint = rtrim(config('socranext.platform_api_url'), '/').'/connect-site';
        abort_unless(parse_url($endpoint, PHP_URL_SCHEME) === 'https', 422, 'The platform endpoint must use HTTPS.');
        try {
            $setup->prepare();
            if ($readiness->automatic() && !$readiness->ready()) return back()->withErrors(['setup' => __('socranext::cp.setup_error')]);
        } catch (\Throwable) {
            return back()->withErrors(['setup' => __('socranext::cp.setup_error')]);
        }
        $state = $connection->begin();
        try {
            $response = Http::asJson()->timeout(30)->withoutRedirecting()->post($endpoint, [
                'site' => rtrim((string) config('socranext.site_url'), '/'), 'cms' => 'statamic', 'state' => $state, 'locale' => app()->getLocale(),
            ]);
            if ($response->successful() && $response->json('success') === true && $connection->connected() && !$connection->pending()) return back()->with('socranext_message', __('socranext::cp.connected_message'));
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            // No tokens, content or raw remote errors enter the user-facing response.
        }
        return back()->withErrors(['connection' => __('socranext::cp.connection_error')]);
    }

    public function disconnect(Connection $connection)
    {
        $connection->disconnect();
        return back()->with('socranext_message', __('socranext::cp.disconnected_message'));
    }

    public function readiness(Request $request, StateStore $store)
    {
        abort_unless(config('socranext.frontend.mode', 'automatic') === 'manual', 409, 'Website readiness is checked automatically.');
        $input = $request->validate(['frontend_ready' => 'required|boolean']);
        $store->put('frontend_ready', (bool) $input['frontend_ready']);
        return back()->with('socranext_message', __('socranext::cp.saved_message'));
    }
}
