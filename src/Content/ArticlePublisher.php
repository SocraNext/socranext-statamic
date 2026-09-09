<?php

namespace SocraNext\Statamic\Content;

use Illuminate\Support\Str;
use SocraNext\Statamic\Rendering\SafeMarkup;
use SocraNext\Statamic\Support\StateStore;
use Statamic\Facades\{Blueprint, Collection, Entry, Site, Taxonomy, Term};

class ArticlePublisher
{
    public function __construct(private ContentRepository $content, private StateStore $store,
        private MutationLock $lock, private AssetImporter $assets, private SafeMarkup $markup, private ArchiveManager $archives) {}

    public function prepare(): void
    {
        $this->lock->run(fn () => $this->prepareUnlocked());
    }

    private function prepareUnlocked(): void
    {
        $this->content->assertSiteConfiguration();
        $handle = $this->content->managedCollection();
        $taxonomy = $this->content->managedTaxonomy();
        $owned = $this->store->get('managed_resources', []);
        if (!isset($owned[$handle])) {
            abort_if(Collection::find($handle) || Taxonomy::find($taxonomy), 409, 'Managed collection or taxonomy name already exists. Choose unused handles.');
            $this->store->transaction(function (array &$data) use ($handle, $taxonomy) {
                $data['managed_resources'][$handle] = ['taxonomy' => $taxonomy];
            });
        } else abort_unless($owned[$handle]['taxonomy'] === $taxonomy, 409, 'Managed taxonomy configuration changed; migrate explicitly.');
        if (!Taxonomy::find($taxonomy)) {
            $term = Taxonomy::make($taxonomy)->title('SocraNext categories')->sites($this->content->sites());
            abort_unless($term->save(), 422, 'Taxonomy creation was rejected.');
        }
        if (!Collection::find($handle)) {
            $collection = Collection::make($handle)->title('SocraNext articles')->sites($this->content->sites())
                ->routes('/'.$this->slug(config('socranext.content.articles_slug', 'artikelen-sn')).'/{socranext_path}')
                ->template(config('socranext.content.article_template', 'socranext::public.entry'))
                ->layout(config('socranext.content.article_layout', 'layout'))->taxonomies([$taxonomy])
                ->revisionsEnabled(true)->propagate(false);
            abort_unless($collection->save(), 422, 'Collection creation was rejected.');
        }
        $collection = Collection::find($handle);
        $routes = $collection->routes()->all();
        $changed = false;
        foreach ($this->content->sites() as $site) if (!in_array($site, $collection->sites()->all(), true)) {
            $collection->sites([...$collection->sites()->all(), $site]);
            $routes[$site] = '/'.($this->store->get('articles_slugs', [])[$site] ?? config('socranext.content.articles_slug', 'artikelen-sn')).'/{socranext_path}';
            $changed = true;
        }
        if ($changed) abort_unless($collection->routes($routes)->save(), 422, 'Collection site configuration was rejected.');
        $taxonomyResource = Taxonomy::find($taxonomy);
        $termSites = array_values(array_unique([...$taxonomyResource->sites()->all(), ...$this->content->sites()]));
        if ($termSites !== $taxonomyResource->sites()->all()) abort_unless($taxonomyResource->sites($termSites)->save(), 422, 'Taxonomy site configuration was rejected.');
        if (!Blueprint::find('collections/'.$handle.'/article')) {
            $fields = [
                ['handle' => 'title', 'field' => ['type' => 'text', 'required' => true, 'localizable' => true]],
                ['handle' => 'content', 'field' => ['type' => 'textarea', 'display' => 'Article HTML', 'localizable' => true]],
                ['handle' => 'featured_image', 'field' => ['type' => 'assets', 'container' => config('socranext.content.asset_container'), 'max_files' => 1, 'localizable' => true]],
                ['handle' => $taxonomy, 'field' => ['type' => 'terms', 'taxonomies' => [$taxonomy], 'localizable' => true]],
            ];
            foreach (['socranext_path', 'socranext_title_tag', 'socranext_meta_description', 'socranext_json_ld', 'socranext_key_takeaways', 'socranext_author'] as $field) {
                $fields[] = ['handle' => $field, 'field' => ['type' => 'textarea', 'localizable' => true]];
            }
            Blueprint::make('article')->setNamespace('collections/'.$handle)
                ->setContents(['title' => 'SocraNext article', 'tabs' => ['main' => ['sections' => [['fields' => $fields]]]]])->save();
        }
        $this->archives->prepare();
    }

    public function publish(array $payload): array
    {
        return $this->mutation(function () use ($payload) {
            $this->prepareUnlocked();
            $site = $this->content->site($payload['site'] ?? null, $payload['language'] ?? null);
            abort_unless(isset($payload['blogId']) && (string) $payload['blogId'] !== '', 422, 'blogId is required for idempotent publication.');
            $key = hash('sha256', json_encode([$site, (string) $payload['blogId']], JSON_THROW_ON_ERROR));
            $bindings = $this->store->get('article_bindings', []);
            $binding = $bindings[$key] ?? null;
            if ($binding && ($binding['deleted'] ?? false)) {
                abort_unless(($binding['purged'] ?? false) && ($payload['restore'] ?? false) === true, 409, 'This publication was deleted; only a purged publication may be explicitly restored.');
                $binding['deleted'] = false;
                $binding['complete'] = false;
                $this->store->transaction(function (array &$data) use ($key, $binding) { $data['article_bindings'][$key] = $binding; });
                $this->content->identities->reactivate($this->content->identities->id('entry', $binding['native_id'], $site));
            }
            $entry = $binding ? Entry::find($binding['native_id']) : null;
            if (!$binding) {
                $matches = Entry::query()->where('collection', $this->content->managedCollection())->where('site', $site)
                    ->where('socranext_blog_id', (string) $payload['blogId'])->get();
                abort_if($matches->count() > 1, 409, 'Multiple native entries share this blogId; reconcile them in Statamic.');
                if ($entry = $matches->first()) {
                    $binding = ['native_id' => $entry->id(), 'site' => $site, 'complete' => true];
                    $this->store->transaction(function (array &$data) use ($key, $binding) { $data['article_bindings'][$key] = $binding; });
                }
            }
            if ($binding && !$entry && ($binding['complete'] ?? false)) abort(409, 'The native article was removed. Restore it or use a new blogId.');
            $source = isset($payload['sourcePostId']) ? $this->content->managed($payload['sourcePostId']) : null;
            if ($source) {
                abort_if($source->locale() === $site, 422, 'A translation must target another connected site.');
                $localized = $source->in($site);
                abort_if($localized && (!$entry || $entry->id() !== $localized->id()), 409, 'A translation already exists in this site.');
                if ($entry) abort_unless($entry->root()->id() === $source->root()->id(), 409, 'Translation origin cannot change.');
            }
            if (!$binding) {
                $binding = ['native_id' => (string) Str::uuid(), 'site' => $site, 'complete' => false];
                $this->store->transaction(function (array &$data) use ($key, $binding) { $data['article_bindings'][$key] = $binding; });
            }
            $exists = $entry !== null;
            if (!$entry) {
                $entry = $source ? $source->root()->makeLocalization($site) : Entry::make()->collection($this->content->managedCollection())->locale($site);
                $entry->id($binding['native_id'])->blueprint('article')->published(false)
                    ->set('socranext_owned', true)->set('socranext_blog_id', (string) $payload['blogId']);
            }
            abort_unless($entry->get('socranext_owned') === true && $entry->collectionHandle() === $this->content->managedCollection(), 409, 'Publication binding no longer points to managed content.');
            $result = $this->write($entry, $payload, $exists);
            $this->store->transaction(function (array &$data) use ($key) { $data['article_bindings'][$key]['complete'] = true; });
            return $result;
        });
    }

    public function update(int|string $id, array $payload): array
    {
        return $this->mutation(fn () => $this->write($this->content->managed($id), $payload, true, true));
    }

    private function write($entry, array $payload, bool $exists, bool $preserveStatus = false): array
    {
        $id = $this->content->identity($entry);
        $sync = $this->store->get('article_sync', [])[$id] ?? [];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $fingerprint = $this->content->fingerprint($entry);
        $oldUrl = $exists ? $entry->absoluteUrl() : null;
        $diskRevision = $exists && is_file($entry->path()) ? hash_file('sha256', $entry->path()) : null;
        if ($exists) {
            // Recover a crash between native save and journal commit only if native data is unchanged.
            if ($entry->get('socranext_operation_hash') === $hash && $entry->get('socranext_sync_revision') === $fingerprint && !$entry->hasWorkingCopy()) {
                $this->store->transaction(function (array &$data) use ($id, $hash, $fingerprint, $entry) {
                    $data['article_sync'][$id] = ['live_revision' => $fingerprint, 'draft_revision' => null, 'payload_hash' => $hash, 'draft' => !$entry->published()];
                });
                return $this->response($entry, !$entry->published());
            }
            $expected = $payload['expected_revision'] ?? $sync['live_revision'] ?? $entry->get('socranext_sync_revision');
            abort_unless($expected && hash_equals($expected, $fingerprint), 409, 'Article was edited in Statamic. Refresh and explicitly reconcile the current revision.');
            if ($entry->hasWorkingCopy()) {
                abort_unless(isset($sync['draft_revision']) && hash_equals($sync['draft_revision'], $this->content->fingerprint($entry->fromWorkingCopy())), 409, 'A Statamic editor has a pending working copy.');
            }
            if (($sync['payload_hash'] ?? $entry->get('socranext_operation_hash')) === $hash) return $this->response($entry, $sync['draft'] ?? !$entry->published());
        }
        $candidate = $preserveStatus && $entry->hasWorkingCopy() ? $entry->fromWorkingCopy() : clone $entry;
        if (array_key_exists('titel', $payload)) $candidate->set('title', $payload['titel']);
        abort_unless(trim((string) $candidate->get('title')) !== '', 422, 'Article title is required.');
        if (array_key_exists('tekst', $payload)) $candidate->set('content', $this->markup->html($payload['tekst']));
        if (!$exists) abort_unless(array_key_exists('tekst', $payload), 422, 'Article body is required.');
        $slug = $this->slug($payload['slug'] ?? $candidate->slug() ?: Str::slug($candidate->get('title')));
        $parent = isset($payload['parentSlug']) && $payload['parentSlug'] !== '' ? $this->slug($payload['parentSlug'], true).'/' : '';
        $path = array_key_exists('parentSlug', $payload) || !$exists ? $parent.$slug : trim(dirname($candidate->get('socranext_path', $slug)), './');
        if ($exists && !array_key_exists('parentSlug', $payload)) $path = ($path !== '' ? $path.'/' : '').$slug;
        $candidate->slug($slug)->set('socranext_path', $path);
        $existingUri = Entry::findByUri($candidate->getQueryableValue('uri'), $candidate->locale());
        abort_if($existingUri && $existingUri->id() !== $candidate->id(), 409, 'Article URL already belongs to another entry.');
        $collision = Entry::query()->where('collection', $candidate->collectionHandle())->where('site', $candidate->locale())
            ->where('socranext_path', $path)->get()->first(fn ($other) => $other->id() !== $candidate->id());
        abort_if($collision, 409, 'An article already uses this URL path.');
        foreach (['titleTag' => 'socranext_title_tag', 'metaDescription' => 'socranext_meta_description',
            'jsonLd' => 'socranext_json_ld', 'keyTakeaways' => 'socranext_key_takeaways', 'author' => 'socranext_author'] as $input => $field) {
            if (array_key_exists($input, $payload)) $candidate->set($field, $payload[$input]);
        }
        if (array_key_exists('featuredImageUrl', $payload)) $candidate->set('featured_image', $this->assets->import($payload));
        if (array_key_exists('categorie', $payload) || !empty($payload['categoryName']) || !empty($payload['sourceTermId'])) {
            $category = null;
            $categoryName = $payload['categoryName'] ?? null;
            if (!empty($payload['sourceTermId'])) {
                $sourceTerm = $this->content->managedTerm($payload['sourceTermId']);
                $category = $sourceTerm->locale() === $candidate->locale() ? $sourceTerm
                    : $this->categoryUnlocked(['name' => $categoryName ?: $sourceTerm->get('title'), 'site' => $candidate->locale(), 'sourceTermId' => $payload['sourceTermId']]);
            } elseif (!empty($payload['categorie'])) {
                if (ctype_digit((string) $payload['categorie'])) {
                    if ($this->content->identities->get($payload['categorie'])) $category = $this->content->managedTerm($payload['categorie']);
                    else abort_unless($categoryName, 422, 'Unknown category identifier; provide categoryName to create it.');
                } else $categoryName ??= (string) $payload['categorie'];
            }
            if (!$category && $categoryName) $category = $this->categoryUnlocked(['name' => $categoryName, 'site' => $candidate->locale()]);
            abort_if($category && $category->locale() !== $candidate->locale(), 422, 'Category must belong to the article site.');
            $candidate->set($this->content->managedTaxonomy(), $category ? [$category->slug()] : []);
        }
        $draft = !array_key_exists('status', $payload) && !array_key_exists('published', $payload)
            ? ($preserveStatus ? !$candidate->published() : false)
            : ($payload['status'] ?? null) === 'draft' || (array_key_exists('published', $payload) && !$payload['published']);
        $candidate->published(!$draft)->set('socranext_operation_hash', $hash);
        $candidate->set('socranext_sync_revision', $this->content->fingerprint($candidate));
        // The image request can take time. Check the native file again before saving.
        abort_if($diskRevision !== null && (!is_file($entry->path()) || !hash_equals($diskRevision, hash_file('sha256', $entry->path()))), 409, 'Article changed during publication. Retry after reconciliation.');
        if ($exists && $entry->published() && $draft) {
            abort_unless($entry->revisionsEnabled(), 422, 'Draft updates require Statamic Pro revisions; the live article was not changed.');
            $candidate->makeWorkingCopy()->save();
            $draftRevision = $this->content->fingerprint($candidate);
        } else {
            if ($exists && $entry->revisionsEnabled()) $entry->makeRevision()->message('Before SocraNext update')->save();
            abort_unless($candidate->save(), 422, 'Statamic rejected the article save.');
            if ($candidate->revisionsEnabled()) $candidate->makeRevision()->message('SocraNext publication')->save();
            $candidate->deleteWorkingCopy();
            $entry = $candidate;
            $draftRevision = null;
        }
        $entry->getQueryableValue('uri');
        $newUrl = $entry->absoluteUrl();
        $this->store->transaction(function (array &$data) use ($id, $hash, $draft, $entry, $draftRevision, $oldUrl, $newUrl) {
            $data['article_sync'][$id] = ['live_revision' => $this->content->fingerprint($entry), 'draft_revision' => $draftRevision,
                'payload_hash' => $hash, 'draft' => $draft];
            if ($oldUrl && $newUrl && $oldUrl !== $newUrl) {
                foreach ($data['redirects'] ?? [] as $old => $target) if ($target === $oldUrl) $data['redirects'][$old] = $newUrl;
                $data['redirects'][$oldUrl] = $newUrl;
                $data['redirects'] = array_filter($data['redirects'], fn ($target, $old) => $target !== $old, ARRAY_FILTER_USE_BOTH);
            }
        });
        return $this->response($entry, $draft);
    }

    private function response($entry, bool $draft): array
    {
        $item = $this->content->describe($entry);
        return [...$item, 'success' => true, 'postId' => $item['id'], 'post_id' => $item['id'], 'postUrl' => $item['url'],
            'draft' => $draft, 'working_copy' => $entry->hasWorkingCopy(), 'status' => $draft ? 'draft' : $item['status']];
    }

    public function createCategory(array $payload): array
    {
        return $this->mutation(function () use ($payload) {
            $this->prepareUnlocked();
            $term = $this->categoryUnlocked($payload);
            return [...$this->content->describe($term), 'name' => $term->get('title'), 'success' => true];
        });
    }

    private function categoryUnlocked(array $payload)
    {
        $site = $this->content->site($payload['site'] ?? null, $payload['language'] ?? null);
        $source = isset($payload['sourceTermId']) ? $this->content->managedTerm($payload['sourceTermId']) : null;
        $slug = $source ? $source->slug() : $this->slug($payload['slug'] ?? Str::slug($payload['name']));
        $term = Term::find($this->content->managedTaxonomy().'::'.$slug);
        $id = $this->content->identities->id('term', $this->content->managedTaxonomy().'::'.$slug, $site);
        $retired = $this->content->identities->get($id)['deleted'];
        abort_if($retired && !($this->store->get('purged_terms', [])[$id] ?? false), 409, 'This category slug was deleted explicitly; choose a new slug.');
        if ($term) {
            $localized = $term->in($site);
            abort_unless($term->inDefaultLocale()->get('socranext_owned') === true || $localized->get('socranext_owned') === true, 409, 'Category slug is already used by unmanaged content.');
            abort_if($source && $source->locale() === $site, 422, 'Category translation must target another site.');
            if ($localized->get('socranext_owned') === true && !$retired) return $localized;
        } else {
            $term = Term::make()->taxonomy($this->content->managedTaxonomy())->slug($slug);
            $term->dataForLocale($term->defaultLocale(), []);
        }
        $localized = $term->in($site)->set('title', $payload['name'])->set('description', $payload['description'] ?? '')
            ->set('socranext_owned', true);
        abort_unless($localized->save(), 422, 'Category save was rejected.');
        if ($retired) $this->content->identities->reactivate($id);
        return $localized;
    }

    public function delete(int|string $id): array
    {
        return $this->mutation(function () use ($id) {
            $entry = $this->content->managed($id);
            // Native delete can affect localization origins. Require explicit child-first deletion.
            abort_if($entry->descendants()->isNotEmpty(), 409, 'Delete article translations before their source.');
            abort_unless($entry->delete(), 422, 'Statamic rejected deletion.');
            $this->retire((int) $id, $entry->id());
            return ['success' => true, 'id' => (int) $id];
        });
    }

    private function retire(int $id, string $native, bool $purged = false): void
    {
        $this->content->identities->tombstone($id);
        $this->store->transaction(function (array &$data) use ($id, $native, $purged) {
            unset($data['article_sync'][$id], $data['faqs'][$id]);
            foreach ($data['article_bindings'] ?? [] as $key => $binding) if ($binding['native_id'] === $native) {
                $data['article_bindings'][$key]['deleted'] = true;
                $data['article_bindings'][$key]['purged'] = $purged;
            }
        });
    }

    public function deleteCategory(int|string $id): array
    {
        return $this->mutation(function () use ($id) {
            $term = $this->content->managedTerm($id);
            $used = Entry::query()->get()
                ->contains(fn ($entry) => in_array($term->slug(), (array) $entry->get($this->content->managedTaxonomy(), []), true));
            abort_if($used, 409, 'Category is still used by an article.');
            // A term slug is shared across locales, so deleting one localization must not delete all.
            abort_if($term->term()->dataForLocale($term->locale())->isEmpty(), 404, 'Category localization does not exist.');
            $otherLocales = $term->taxonomy()->sites()->all();
            $hasOther = collect($otherLocales)->contains(fn ($site) => $site !== $term->locale() && $term->term()->dataForLocale($site)->isNotEmpty());
            if ($hasOther) {
                $term->term()->dataForLocale($term->locale(), []);
                abort_unless($term->term()->save(), 422, 'Statamic rejected category deletion.');
            } else abort_unless($term->delete(), 422, 'Statamic rejected category deletion.');
            $this->content->identities->tombstone((int) $id);
            return ['success' => true, 'id' => (int) $id];
        });
    }

    public function purge(): array
    {
        return $this->mutation(function () {
            $entries = Entry::query()->where('collection', $this->content->managedCollection())->get()
                ->filter(fn ($entry) => $entry->get('socranext_owned') === true && in_array($entry->locale(), $this->content->sites(), true));
            foreach ($entries as $entry) abort_if($entry->descendants()->contains(fn ($child) => !$entries->contains(fn ($owned) => $owned->id() === $child->id())), 409, 'An unmanaged translation depends on a managed article; detach it before purging.');
            $deleted = 0;
            foreach ($entries->sortByDesc(fn ($entry) => $entry->ancestors()->count()) as $entry) {
                $id = $this->content->identity($entry);
                abort_unless($entry->delete(), 422, 'Statamic rejected deletion. Purge can be retried.');
                $this->retire($id, $entry->id(), true);
                $deleted++;
            }
            $deletedCategories = 0;
            $remaining = Entry::query()->get();
            $taxonomy = $this->content->managedTaxonomy();
            $terms = Taxonomy::find($taxonomy) ? Term::query()->where('taxonomy', $taxonomy)->get() : collect();
            foreach ($terms as $term) {
                if ($term instanceof \Statamic\Taxonomies\LocalizedTerm) $term = $term->term();
                // Native customer content may share this term; keep such categories intact.
                if ($remaining->contains(fn ($entry) => in_array($term->slug(), (array) $entry->get($this->content->managedTaxonomy(), []), true))) continue;
                foreach ($term->localizations() as $localized) {
                    if (!in_array($localized->locale(), $this->content->sites(), true) || $localized->get('socranext_owned') !== true) continue;
                    $id = $this->content->identity($localized);
                    $term->dataForLocale($localized->locale(), []);
                    $this->content->identities->tombstone($id);
                    $this->store->transaction(function (array &$data) use ($id) { $data['purged_terms'][$id] = true; });
                    $deletedCategories++;
                }
                $hasData = $term->localizations()->contains(fn ($localized) => $localized->data()->isNotEmpty());
                abort_unless($hasData ? $term->save() : $term->delete(), 422, 'Category purge was rejected; retry purge.');
            }
            // Preserve asset files: they may be referenced by customer-owned entries.
            $this->store->forget('styles');
            $this->store->forget('faqs');
            $this->store->forget('llms');
            $this->store->forget('metadata');
            return ['success' => true, 'deleted' => $deleted, 'deleted_categories' => $deletedCategories];
        });
    }

    public function collectionSlugs(array $payload): array
    {
        return $this->mutation(function () use ($payload) {
            $this->prepareUnlocked();
            $slug = $this->slug($payload['articles_slug']);
            $site = $this->content->site($payload['site'] ?? null, $payload['language'] ?? null);
            $collection = Collection::find($this->content->managedCollection());
            $routes = $collection->routes()->all();
            $newRoute = '/'.$slug.'/{socranext_path}';
            foreach (Collection::all() as $other) abort_if($other->handle() !== $collection->handle() && $other->route($site) === $newRoute, 409, 'Another collection uses this route.');
            foreach ($collection->queryEntries()->where('site', $site)->get() as $entry) {
                $other = Entry::findByUri('/'.$slug.'/'.$entry->get('socranext_path', $entry->slug()), $site);
                abort_if($other && $other->id() !== $entry->id() && $other->collectionHandle() !== $collection->handle(), 409, 'A moved article URL would collide with customer content.');
            }
            $oldUrls = $collection->queryEntries()->where('site', $site)->get()->mapWithKeys(fn ($entry) => [$entry->id() => $entry->absoluteUrl()])->all();
            $archiveRedirects = $this->archives->rename($site, $slug);
            $routes[$site] = $newRoute;
            abort_unless($collection->routes($routes)->save(), 422, 'Collection route update was rejected.');
            \Statamic\Facades\Blink::flush();
            $redirects = $archiveRedirects;
            foreach ($oldUrls as $native => $old) {
                $new = Entry::find($native)->absoluteUrl();
                if ($old && $new && $old !== $new) $redirects[$old] = $new;
            }
            $this->store->transaction(function (array &$data) use ($redirects, $site, $slug) {
                $existing = $data['redirects'] ?? [];
                foreach ($existing as $old => $target) if (isset($redirects[$target])) $existing[$old] = $redirects[$target];
                $data['redirects'] = array_filter(array_replace($existing, $redirects), fn ($target, $old) => $target !== $old, ARRAY_FILTER_USE_BOTH);
                $data['articles_slugs'][$site] = $slug;
            });
            return ['success' => true, 'articles_slug' => $slug, 'site' => $site, 'redirects' => count($redirects)];
        });
    }

    private function slug(string $slug, bool $path = false): string
    {
        $slug = trim($slug, '/');
        abort_unless(strlen($slug) <= 200 && preg_match($path ? '~^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$~i' : '~^[a-z0-9_-]+$~i', $slug), 422, 'Slug must contain letters, digits, hyphens or underscores.');
        return $slug;
    }

    private function mutation(callable $callback): array
    {
        $this->content->assertSiteConfiguration();
        $result = $this->lock->run($callback);
        if (config('statamic.static_caching.strategy')) \Statamic\Facades\StaticCache::flush();
        return $result;
    }
}
