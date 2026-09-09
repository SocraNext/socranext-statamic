<?php

namespace SocraNext\Statamic\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use SocraNext\Statamic\ServiceProvider;
use SocraNext\Statamic\Support\Connection;
use SocraNext\Statamic\Support\StateStore;
use SocraNext\Statamic\Support\Readiness;
use SocraNext\Statamic\Content\ContentRepository;
use Statamic\Facades\{AssetContainer, Collection, Site, Taxonomy};
use function Statamic\trans as __;

class ControlPanelController
{
    public function index(Connection $connection, StateStore $store, Readiness $readiness, ContentRepository $content)
    {
        $sitesValid = true;
        try { $sites = $content->sites(); } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { $sitesValid = false; $sites = []; }
        $siteUrl = trim((string) config('socranext.site_url'));
        $https = parse_url($siteUrl, PHP_URL_SCHEME) === 'https' && (bool) parse_url($siteUrl, PHP_URL_HOST);
        $collectionHandle = config('socranext.content.managed_collection', 'socranext_articles');
        $taxonomyHandle = config('socranext.content.managed_taxonomy', 'socranext_categories');
        $assetHandle = config('socranext.content.asset_container', 'socranext');
        $managed = $store->get('managed_resources', []);
        $binding = is_array($managed) && is_string($collectionHandle) ? ($managed[$collectionHandle] ?? null) : null;
        // Match the installer's ownership contract: existing customer resources
        // with these handles are a collision, not a completed installation.
        $owned = is_array($binding) && ($binding['taxonomy'] ?? null) === $taxonomyHandle;
        $checks = [
            'website_address' => $https,
            'article_collection' => $owned && is_string($collectionHandle) && $collectionHandle !== ''
                && is_string($taxonomyHandle) && $taxonomyHandle !== ''
                && Collection::find($collectionHandle) !== null && Taxonomy::find($taxonomyHandle) !== null,
            'image_storage' => is_string($assetHandle) && $assetHandle !== '' && AssetContainer::find($assetHandle) !== null,
            'languages' => $sitesValid,
        ];
        return response()->view('socranext::cp.index', [
            'connected' => $connection->connected(), 'ready' => $readiness->ready(),
            'frontendChecked' => (bool) (config('socranext.frontend_ready') || $store->get('frontend_ready', false)),
            'version' => ServiceProvider::VERSION, 'setupChecks' => $checks,
            'setupComplete' => !in_array(false, $checks, true),
            'websiteUrl' => $https ? $siteUrl : null,
            'websiteLabel' => parse_url($siteUrl, PHP_URL_HOST) ?: __('socranext::cp.no_website'),
            'siteNames' => array_map(fn ($handle) => Site::get($handle)->name(), $sites),
        ]);
    }

    public function connect(Request $request, Connection $connection)
    {
        $state = $connection->begin();
        $endpoint = rtrim(config('socranext.platform_api_url'), '/').'/connect-site';
        abort_unless(parse_url($endpoint, PHP_URL_SCHEME) === 'https', 422, 'The platform endpoint must use HTTPS.');
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
        $input = $request->validate(['frontend_ready' => 'required|boolean']);
        $store->put('frontend_ready', (bool) $input['frontend_ready']);
        return back()->with('socranext_message', __('socranext::cp.saved_message'));
    }
}
