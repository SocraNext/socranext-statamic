<?php

namespace SocraNext\Statamic\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use SocraNext\Statamic\ServiceProvider;
use SocraNext\Statamic\Support\Connection;
use SocraNext\Statamic\Support\StateStore;

class ControlPanelController
{
    public function index(Connection $connection, StateStore $store)
    {
        return response()->view('socranext::cp.index', ['connected' => $connection->connected(), 'ready' => config('socranext.frontend_ready') || $store->get('frontend_ready', false), 'version' => ServiceProvider::VERSION]);
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
            if ($response->successful() && $response->json('success') === true && $connection->connected() && !$connection->pending()) return back()->with('socranext_message', 'SocraNext is connected.');
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            // No tokens, content or raw remote errors enter the user-facing response.
        }
        return back()->withErrors(['connection' => 'Connection could not be completed. Check that this website has a SocraNext project, then try again.']);
    }

    public function disconnect(Connection $connection)
    {
        $connection->disconnect();
        return back()->with('socranext_message', 'Disconnected. Your published content has been kept.');
    }

    public function readiness(Request $request, StateStore $store)
    {
        $input = $request->validate(['frontend_ready' => 'required|boolean']);
        $store->put('frontend_ready', (bool) $input['frontend_ready']);
        return back()->with('socranext_message', 'Website configuration status saved.');
    }
}
