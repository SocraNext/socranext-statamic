<?php

namespace SocraNext\Statamic\Support;

use SocraNext\Statamic\Content\ContentRepository;
use SocraNext\Statamic\Rendering\SiteViewFinder;
use Statamic\Facades\{AssetContainer, Blueprint, Collection, Entry, Taxonomy};

class Readiness
{
    public function __construct(private StateStore $store) {}

    public function automatic(): bool
    {
        return config('socranext.frontend.mode', 'automatic') === 'automatic';
    }

    public function ready(): bool
    {
        if ($this->automatic()) return !in_array(false, $this->checks(), true);
        if (config('socranext.frontend.mode') !== 'manual'
            || !(config('socranext.frontend_ready') || $this->store->get('frontend_ready', false))) return false;
        try {
            app(ContentRepository::class)->assertSiteConfiguration();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return false;
        }
        return true;
    }

    /** Read-only checks shared by the API, control panel and doctor command. */
    public function checks(): array
    {
        $url = (string) config('socranext.site_url');
        $checks = ['website_address' => parse_url($url, PHP_URL_SCHEME) === 'https' && (bool) parse_url($url, PHP_URL_HOST),
            'article_collection' => false, 'image_storage' => false, 'languages' => false,
            'templates' => false, 'native_routes' => (bool) config('statamic.routes.enabled', true)];
        $content = app(ContentRepository::class);
        try {
            $sites = $content->sites();
            $checks['languages'] = true;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            $sites = [];
        }
        $handle = config('socranext.content.managed_collection');
        $taxonomy = config('socranext.content.managed_taxonomy');
        $archiveHandle = config('socranext.content.archive_collection', 'socranext_archives');
        $collection = is_string($handle) && $handle !== '' ? Collection::find($handle) : null;
        $taxonomyResource = is_string($taxonomy) && $taxonomy !== '' ? Taxonomy::find($taxonomy) : null;
        $archive = is_string($archiveHandle) && $archiveHandle !== '' ? Collection::find($archiveHandle) : null;
        $binding = is_string($handle) ? ($this->store->get('managed_resources', [])[$handle] ?? null) : null;
        $owned = is_array($binding) && ($binding['taxonomy'] ?? null) === $taxonomy;
        $checks['article_collection'] = $owned && $collection !== null && $taxonomyResource !== null && $archive !== null
            && Blueprint::find('collections/'.$handle.'/article') !== null
            && $this->store->get('managed_archive') === $archiveHandle && $sites !== [];
        foreach ([$collection, $taxonomyResource, $archive] as $resource) {
            $checks['article_collection'] = $checks['article_collection'] && $resource !== null
                && !array_diff($sites, $resource->sites()->all());
        }
        foreach ($sites as $site) {
            $entryId = $this->store->get('archive_entries', [])[$site] ?? null;
            $entry = is_string($entryId) ? Entry::find($entryId) : null;
            $checks['article_collection'] = $checks['article_collection'] && $entry !== null
                && $entry->collectionHandle() === $archiveHandle && $entry->locale() === $site
                && $entry->get('socranext_archive') === true && $entry->get('socranext_owned') === true
                && $content->publiclyDiscoverable($entry) && (bool) $collection?->route($site);
        }
        if ($collection && $archive && $sites !== []) {
            $checks['templates'] = true;
            foreach ($sites as $site) {
                $finder = SiteViewFinder::forSite($site);
                foreach ([$collection, $archive] as $resource) {
                    $template = SiteViewFinder::find($finder, $resource->template(), 'templates');
                    // Native Blade/PHP templates manage their own layout; only Antlers uses the collection layout.
                    $usesLayout = $template !== null && collect(\Statamic\View\Antlers\Engine::EXTENSIONS)
                        ->contains(fn ($extension) => str_ends_with($template, '.'.$extension));
                    $checks['templates'] = $checks['templates'] && $template !== null
                        && (!$usesLayout || SiteViewFinder::find($finder, $resource->layout(), 'layouts') !== null);
                }
            }
        }
        try {
            $assetHandle = config('socranext.content.asset_container');
            $container = is_string($assetHandle) && $assetHandle !== '' ? AssetContainer::find($assetHandle) : null;
            if ($container) Setup::assertPublicContainer($container);
            $checks['image_storage'] = $container !== null;
        } catch (\Throwable) {
            // An invalid filesystem configuration should appear as a setup issue.
            $checks['image_storage'] = false;
        }
        return $checks;
    }
}
