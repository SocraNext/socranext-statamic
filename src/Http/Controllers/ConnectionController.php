<?php

namespace SocraNext\Statamic\Http\Controllers;

use Illuminate\Http\Request;
use SocraNext\Statamic\ServiceProvider;
use SocraNext\Statamic\Support\Connection;
use SocraNext\Statamic\Support\StateStore;
use SocraNext\Statamic\Support\Readiness;

class ConnectionController
{
    public function receive(Request $request, Connection $connection)
    {
        $input = $request->validate(['state' => 'required|string|size:64', 'token' => 'required|string|min:32|max:512']);
        if (!$connection->receive($input['state'], $input['token'])) return response()->json(['ok' => false, 'code' => 'invalid_state'], 403);
        return response()->json(['ok' => true]);
    }

    public function status(Readiness $readiness)
    {
        return response()->json([
            'cms_type' => 'statamic', 'contract_version' => 1, 'addon_version' => ServiceProvider::VERSION,
            'frontend_ready' => $readiness->ready(),
            'capabilities' => [
                'articles' => true, 'faq' => true, 'styling' => true, 'collection_slugs' => true,
                'llms_txt' => true, 'translations' => true, 'article_translations' => true,
                'entry_metadata' => true, 'styling_i18n' => true, 'category_slug_update' => false,
                'cms_generation_requests' => false, 'collection_slugs_i18n' => false,
                'live_preview' => true, 'faq_render_mode' => false, 'purge' => true, 'signed_code' => true,
            ],
        ]);
    }
}
