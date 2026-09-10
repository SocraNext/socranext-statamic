<?php

return [
    'platform_url' => env('SOCRANEXT_PLATFORM_URL', 'https://platform.socranext.ai'),
    'platform_api_url' => env('SOCRANEXT_PLATFORM_API_URL', 'https://backend.socranext.ai'),
    'site_url' => env('SOCRANEXT_SITE_URL', env('APP_URL')),
    'state_path' => storage_path('app/private/socranext/state.json'),
    'connect_ttl' => 300,
    'frontend_ready' => false,
    // Native Statamic pages work without adding tags to the site's templates.
    // Use "manual" only for custom placement or a separately hosted frontend.
    'frontend' => ['mode' => 'automatic'],
    'preview_origins' => ['https://platform.socranext.ai'],
    'preview_ttl' => 600,
    // Public Ed25519 key used by the existing SocraNext connectors. Never store a private key here.
    'code_signing_public_key' => 'r3ViJzAlnBu4pi3zjWAV/1AbxkANsYxisbWyP8cPStg=',
    'content' => [
        'discovery' => 'automatic',
        'managed_collection' => 'socranext_articles',
        'managed_taxonomy' => 'socranext_categories',
        'collections' => ['pages' => ['pages'], 'posts' => ['blog'], 'products' => [], 'custom' => []],
        'taxonomies' => [],
        'sites' => [],
        'asset_container' => 'socranext',
        'asset_max_bytes' => 10485760,
        'article_template' => 'socranext::public.entry',
        'article_layout' => 'layout',
        'articles_slug' => 'artikelen-sn',
    ],
];
