<?php

namespace SocraNext\Statamic\Content;

use Illuminate\Support\Str;
use SocraNext\Statamic\Support\StateStore;
use Statamic\Facades\{Collection, Entry};

/** A native archive entry lets Statamic route dynamic archive slugs, including with route caching. */
class ArchiveManager
{
    public function __construct(private ContentRepository $content, private StateStore $store) {}

    public function prepare(): void
    {
        $handle = config('socranext.content.archive_collection', 'socranext_archives');
        if (!$this->store->get('managed_archive')) {
            abort_if(Collection::find($handle), 409, 'Archive collection handle already exists. Choose an unused handle.');
            $this->store->put('managed_archive', $handle);
        }
        abort_unless($this->store->get('managed_archive') === $handle, 409, 'Archive collection configuration changed; migrate explicitly.');
        if (!Collection::find($handle)) abort_unless(Collection::make($handle)->title('SocraNext archives')->sites($this->content->sites())
            ->routes('/{slug}')->template('socranext::public.archive-entry')->layout(config('socranext.content.article_layout', 'layout'))->save(), 422, 'Archive collection could not be created.');
        $collection = Collection::find($handle);
        $sites = array_values(array_unique([...$collection->sites()->all(), ...$this->content->sites()]));
        if ($sites !== $collection->sites()->all()) abort_unless($collection->sites($sites)->save(), 422, 'Archive site configuration was rejected.');
        foreach ($this->content->sites() as $site) {
            $slug = $this->store->get('articles_slugs', [])[$site] ?? config('socranext.content.articles_slug', 'artikelen-sn');
            $this->ensureEntry($handle, $site, $slug);
        }
    }

    private function ensureEntry(string $handle, string $site, string $slug)
    {
        $native = $this->store->get('archive_entries', [])[$site] ?? null;
        $entry = $native ? Entry::find($native) : null;
        if ($entry) {
            abort_unless($entry->collectionHandle() === $handle && $entry->get('socranext_archive') === true, 409, 'Archive binding no longer belongs to SocraNext.');
            return $entry;
        }
        $this->assertAvailable($site, $slug);
        if (!$native) {
            $native = (string) Str::uuid();
            $this->store->transaction(function (array &$data) use ($site, $native) { $data['archive_entries'][$site] = $native; });
        }
        $entry = Entry::make()->id($native)->collection($handle)->locale($site)->slug($slug)->published(true)
            ->set('title', 'Articles')->set('socranext_archive', true)->set('socranext_owned', true);
        abort_unless($entry->save(), 422, 'Archive entry could not be created.');
        return $entry;
    }

    public function rename(string $site, string $slug): array
    {
        $handle = config('socranext.content.archive_collection', 'socranext_archives');
        $entry = $this->ensureEntry($handle, $site, $this->store->get('articles_slugs', [])[$site] ?? config('socranext.content.articles_slug', 'artikelen-sn'));
        $this->assertAvailable($site, $slug, $entry->id());
        $old = $entry->absoluteUrl();
        abort_unless($entry->slug($slug)->save(), 422, 'Archive slug change was rejected.');
        $new = $entry->absoluteUrl();
        return $old && $new && $old !== $new ? [$old => $new] : [];
    }

    private function assertAvailable(string $site, string $slug, ?string $native = null): void
    {
        $existing = Entry::findByUri('/'.$slug, $site);
        abort_if($existing && $existing->id() !== $native, 409, 'Archive URL already belongs to another entry.');
    }
}
