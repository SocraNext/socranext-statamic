<?php

namespace SocraNext\Statamic\Content;

use SocraNext\Statamic\Support\StateStore;
use Statamic\Facades\{Collection, Entry, Site, Taxonomy, Term};

class ContentRepository
{
    public function __construct(public IdentityMap $identities, private StateStore $store) {}

    public function managedCollection(): string
    {
        return config('socranext.content.managed_collection', 'socranext_articles');
    }

    public function managedTaxonomy(): string
    {
        return config('socranext.content.managed_taxonomy', 'socranext_categories');
    }

    public function taxonomies(): array
    {
        return array_values(array_unique([...config('socranext.content.taxonomies', []), $this->managedTaxonomy()]));
    }

    public function sites(): array
    {
        $this->assertSiteConfiguration();
        $configured = config('socranext.content.sites', []);
        $handles = $configured ?: [Site::default()->handle()];
        return array_values(array_unique($handles));
    }

    /** Declared site records do not enable Statamic's native multisite storage. */
    public function assertSiteConfiguration(): void
    {
        $configured = config('socranext.content.sites', []);
        abort_unless(is_array($configured), 422, 'SocraNext content.sites must be an array of native Statamic site handles.');
        $default = Site::default()->handle();
        $handles = $configured ?: [$default];
        foreach ($handles as $handle) {
            abort_unless(is_string($handle) && Site::get($handle) !== null, 422, 'SocraNext content.sites contains an unknown Statamic site handle.');
        }
        abort_if(!Site::multiEnabled() && array_diff($handles, [$default]), 422,
            'Statamic multisite is disabled. Run php please multisite to convert the website before connecting non-default sites, or configure SocraNext content.sites with only the default site.');
    }

    public function languageCode(string $site): string
    {
        $lang = Site::get($site)->lang();
        $matches = array_filter($this->sites(), fn ($handle) => Site::get($handle)->lang() === $lang);
        if (count($matches) === 1) return $lang;
        $locale = str_replace('_', '-', Site::get($site)->locale());
        $matches = array_filter($this->sites(), fn ($handle) => str_replace('_', '-', Site::get($handle)->locale()) === $locale);
        return count($matches) === 1 ? $locale : $site;
    }

    /** A regional locale is not a site identity; ambiguous matches are rejected. */
    public function site(?string $handle = null, ?string $language = null): string
    {
        if ($handle !== null && $handle !== '') {
            abort_unless(in_array($handle, $this->sites(), true), 422, 'Site is not connected.');
            return $handle;
        }
        if ($language !== null && $language !== '') {
            $language = str_replace('_', '-', strtolower($language));
            $matches = array_values(array_filter($this->sites(), function ($site) use ($language) {
                $locale = str_replace('_', '-', strtolower(Site::get($site)->locale()));
                return strtolower($site) === $language || $locale === $language || strtolower(Site::get($site)->lang()) === $language;
            }));
            abort_unless(count($matches) === 1, 422, 'Language does not identify exactly one connected site; supply site.');
            return $matches[0];
        }
        $default = Site::default()->handle();
        return in_array($default, $this->sites(), true) ? $default : ($this->sites()[0] ?? abort(422, 'No site configured.'));
    }

    public function collections(string $type, ?string $cpt = null): array
    {
        if ($type === 'blogs') $type = 'posts';
        if ($type === 'custom' && $cpt === 'socranext_post') return [$this->managedCollection()];
        if ($type === 'custom') {
            $handles = config('socranext.content.collections.custom', []);
            return $cpt !== null && in_array($cpt, $handles, true) ? [$cpt] : [];
        }
        if (!in_array($type, ['pages', 'posts', 'products'], true)) return [];
        return config("socranext.content.collections.$type", $type === 'pages' ? ['pages'] : ($type === 'posts' ? ['blog'] : []));
    }

    public function identity($resource): int
    {
        $this->assertSiteConfiguration();
        return $this->identities->id($this->isTerm($resource) ? 'term' : 'entry', (string) $resource->id(), $resource->locale());
    }

    public function isTerm($resource): bool
    {
        return $resource instanceof \Statamic\Taxonomies\LocalizedTerm || $resource instanceof \Statamic\Taxonomies\Term;
    }

    public function resolve(string $type, int|string $id, ?string $cpt = null)
    {
        $this->assertSiteConfiguration();
        $record = $this->identities->get($id);
        abort_unless($record && !$record['deleted'] && in_array($record['site'], $this->sites(), true), 404, 'Content not found.');
        if ($record['kind'] === 'term') {
            $resource = Term::find($record['native_id']);
            abort_unless($resource && $type === 'categories' && in_array($resource->taxonomyHandle(), $this->taxonomies(), true), 404, 'Category not exposed.');
            return $resource->in($record['site']);
        }
        $resource = Entry::find($record['native_id']);
        abort_unless($resource && $resource->locale() === $record['site'] && in_array($resource->collectionHandle(), $this->collections($type, $cpt), true), 404, 'Content not exposed.');
        return $resource;
    }

    public function managed(int|string $id)
    {
        $resource = $this->resolve('custom', $id, 'socranext_post');
        abort_unless($resource->get('socranext_owned') === true, 403, 'Only SocraNext-owned entries may be changed.');
        return $resource;
    }

    public function managedTerm(int|string $id)
    {
        $this->assertSiteConfiguration();
        $record = $this->identities->get($id);
        $term = $record && !$record['deleted'] && $record['kind'] === 'term' ? Term::find($record['native_id']) : null;
        abort_unless($term && $term->taxonomyHandle() === $this->managedTaxonomy() && in_array($record['site'], $this->sites(), true), 404, 'Managed category not found.');
        $term = $term->in($record['site']);
        abort_unless($term->get('socranext_owned') === true, 403, 'Category is not owned by SocraNext.');
        return $term;
    }

    public function describe($resource): array
    {
        $term = $this->isTerm($resource);
        $id = $this->identity($resource);
        $site = $resource->locale();
        $type = $term ? 'categories' : 'custom';
        $cpt = $term ? $resource->taxonomyHandle() : $resource->collectionHandle();
        if (!$term) {
            if ($cpt === $this->managedCollection()) $cpt = 'socranext_post';
            else foreach (['pages', 'posts', 'products'] as $candidate) {
                if (in_array($resource->collectionHandle(), $this->collections($candidate), true)) {
                    $type = $candidate === 'posts' ? 'blogs' : $candidate;
                    break;
                }
            }
        }
        $faq = $this->store->get('faqs', [])[$id] ?? [];
        $metadata = $this->store->get('metadata', [])[$id] ?? [];
        if (!$term) $resource->getQueryableValue('uri');
        $url = $resource->absoluteUrl();
        return [
            'id' => $id, 'ID' => $id, 'native_id' => (string) $resource->id(), 'site' => $site,
            'title' => (string) $resource->get('title', $resource->slug()), 'slug' => $resource->slug(),
            'url' => $url, 'link' => $url, 'post_type' => $cpt, 'resource_type' => $type,
            'type' => $type, 'language' => $this->languageCode($site), 'lang' => $this->languageCode($site),
            'published' => $term || $resource->status() === 'published',
            'status' => $term ? 'published' : $resource->status(),
            'aio_enabled' => (bool) ($faq['enabled'] ?? false), '_socranext_enabled' => (bool) ($faq['enabled'] ?? false),
            'content' => is_string($resource->get('content', '')) ? $resource->get('content', '') : json_encode($resource->get('content'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'meta_description' => (string) ($metadata['meta_description'] ?? $resource->get('socranext_meta_description', '')),
            'title_tag' => (string) ($metadata['title_tag'] ?? $resource->get('socranext_title_tag', '')),
            'revision' => $this->fingerprint($resource),
        ];
    }

    public function listing(string $type, array $params = [], ?string $cpt = null): array
    {
        $site = $this->site($params['site'] ?? null, $params['lang'] ?? null);
        $search = mb_strtolower(trim((string) ($params['search'] ?? '')));
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 100)));
        if ($type === 'categories') {
            $items = collect();
            foreach ($this->taxonomies() as $handle) {
                if (!Taxonomy::find($handle)) continue;
                $items = $items->concat(Term::query()->where('taxonomy', $handle)->get()->map(fn ($term) => $term->in($site)));
            }
        } else {
            $handles = array_values(array_filter($this->collections($type, $cpt), fn ($handle) => Collection::find($handle) !== null));
            $items = $handles ? Entry::query()->whereIn('collection', $handles)->where('site', $site)->where('published', true)->get() : collect();
            $items = $items->filter(fn ($entry) => $entry->status() === 'published' && !$entry->private());
        }
        $items = $items->filter(fn ($item) => !$item->private() && $item->absoluteUrl());
        if ($search !== '') {
            $items = $items->filter(fn ($item) => str_contains(mb_strtolower((string) $item->get('title')), $search)
                || str_contains(mb_strtolower((string) $item->slug()), $search)
                || rtrim(mb_strtolower((string) $item->absoluteUrl()), '/') === rtrim($search, '/'));
        }
        $items = $items->sortBy(fn ($item) => mb_strtolower((string) $item->get('title')).':'.$item->id())->values();
        $total = $items->count();
        return ['items' => $items->slice(($page - 1) * $perPage, $perPage)->map(fn ($item) => $this->describe($item))->values()->all(),
            'total' => $total, 'totalPages' => (int) ceil($total / $perPage)];
    }

    public function fingerprint($entry): string
    {
        $data = $entry->data()->except(['updated_at', 'updated_by', 'socranext_sync_revision'])->all();
        // Native file loading places the blueprint in data, while newly saved
        // entries can keep it only as a property. Hash the same resolved value.
        $data['blueprint'] = $entry->blueprint()->handle();
        if (!$this->isTerm($entry)) {
            if ($entry->isRoot()) $data = \Statamic\Support\Arr::removeNullValues($data);
            // Statamic stores string content after the YAML front matter and
            // removes its leading whitespace when reading that native file.
            if (isset($data['content']) && is_string($data['content'])) {
                $data['content'] = ltrim($data['content']);
                if (empty($data['content'])) unset($data['content']);
            }
        }
        ksort($data);
        return hash('sha256', json_encode([$entry->slug(), $entry->published(), $data], JSON_THROW_ON_ERROR));
    }
}
