<?php

namespace SocraNext\Statamic\Support;

use SocraNext\Statamic\Content\{ArticlePublisher, ContentRepository, MutationLock};
use SocraNext\Statamic\Rendering\FrontendLayout;
use Statamic\Facades\{AssetContainer, Blueprint, Collection, Entry, Taxonomy};

/** Explicit install/connect setup. Registering the disk alone never writes files. */
class Setup
{
    public const DEFAULT_CONTAINER = 'socranext';
    public const DEFAULT_DISK = 'socranext_public';

    public function __construct(private ArticlePublisher $publisher, private ContentRepository $content,
        private MutationLock $lock, private StateStore $store) {}

    public static function registerDefaultDisk(): void
    {
        $key = 'filesystems.disks.'.self::DEFAULT_DISK;
        if (config()->has($key)) return;
        config([$key => self::defaultDisk()]);
    }

    private static function defaultDisk(): array
    {
        return ['driver' => 'local', 'root' => public_path('socranext-assets'),
            'url' => rtrim((string) (config('socranext.site_url') ?: config('app.url')), '/').'/socranext-assets',
            'visibility' => 'public', 'throw' => true];
    }

    public function isComplete(): bool
    {
        return $this->store->get('setup_complete', false) === true;
    }

    public function prepare(): array
    {
        self::registerDefaultDisk();
        // Check every namespace and destination before creating any native resource.
        $complete = $this->lock->run(function () {
            $complete = $this->isComplete();
            $this->preflightContent($complete);
            if (!Collection::find($this->content->managedCollection())
                || !Collection::find(config('socranext.content.archive_collection', 'socranext_archives'))) {
                FrontendLayout::resolve();
            }
            $container = $this->preflightAssets();
            abort_if($complete && !$container, 409, 'The installed image container was removed. Restore it before reconnecting.');
            return $complete;
        });
        if ($complete) return $this->result(false);
        // Distinguish our retryable first installation from an older installed site.
        $this->store->put('setup_in_progress', true);
        // ArticlePublisher already takes this same non-reentrant mutation lock.
        $this->publisher->prepare();
        $created = $this->lock->run(fn () => $this->prepareAssets());
        $this->store->transaction(function (array &$data) {
            $data['setup_complete'] = true;
            unset($data['setup_in_progress']);
        });
        return $this->result($created);
    }

    private function result(bool $created): array
    {
        return ['content_prepared' => true, 'asset_container_created' => $created,
            'asset_container' => config('socranext.content.asset_container', self::DEFAULT_CONTAINER)];
    }

    private function preflightContent(bool $complete): void
    {
        $this->content->assertSiteConfiguration();
        $handle = $this->content->managedCollection();
        $taxonomy = $this->content->managedTaxonomy();
        $archive = config('socranext.content.archive_collection', 'socranext_archives');
        foreach ([$handle, $taxonomy, $archive] as $name) abort_unless(is_string($name) && preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $name), 422, 'SocraNext resource handles are invalid.');
        abort_if($handle === $archive, 409, 'Article and archive collections need distinct handles.');
        $owned = $this->store->get('managed_resources', [])[$handle] ?? null;
        if (!$owned) {
            abort_if(Collection::find($handle) || Taxonomy::find($taxonomy) || Blueprint::find('collections/'.$handle.'/article'),
                409, 'SocraNext article resources already belong to the website. Choose unused handles.');
        } else abort_unless(($owned['taxonomy'] ?? null) === $taxonomy, 409, 'Managed taxonomy configuration changed; migrate explicitly.');
        $ownedArchive = $this->store->get('managed_archive');
        abort_if(!$ownedArchive && Collection::find($archive), 409, 'Archive collection handle already exists. Choose an unused handle.');
        abort_if($ownedArchive && $ownedArchive !== $archive, 409, 'Archive collection configuration changed; migrate explicitly.');
        $installed = $complete || (($owned || $ownedArchive) && !$this->store->get('setup_in_progress', false));
        if ($installed) {
            abort_unless($owned && $ownedArchive && Collection::find($handle) && Taxonomy::find($taxonomy)
                && Collection::find($archive) && Blueprint::find('collections/'.$handle.'/article'),
                409, 'Installed SocraNext resources were removed. Restore them before reconnecting.');
            foreach ([Collection::find($handle), Taxonomy::find($taxonomy), Collection::find($archive)] as $resource) {
                abort_if(array_diff($this->content->sites(), $resource->sites()->all()), 409, 'Installed SocraNext site configuration changed; migrate explicitly.');
            }
        }
        foreach ($this->content->sites() as $site) {
            $slug = $this->store->get('articles_slugs', [])[$site] ?? config('socranext.content.articles_slug', 'artikelen-sn');
            abort_unless(is_string($slug) && strlen($slug) <= 200 && preg_match('/^[A-Za-z0-9_-]+$/D', $slug), 422, 'The article archive slug is invalid.');
            $native = $this->store->get('archive_entries', [])[$site] ?? null;
            $entry = $native ? Entry::find($native) : null;
            if ($entry) {
                abort_unless($entry->collectionHandle() === $archive && $entry->get('socranext_archive') === true, 409, 'Archive binding no longer belongs to SocraNext.');
                continue;
            }
            abort_if($installed, 409, 'The installed article archive was removed. Restore it before reconnecting.');
            $existing = Entry::findByUri('/'.$slug, $site);
            abort_if($existing && $existing->id() !== $native, 409, 'The article archive URL already belongs to another page.');
        }
    }

    private function preflightAssets(): ?object
    {
        $handle = config('socranext.content.asset_container', self::DEFAULT_CONTAINER);
        abort_unless(is_string($handle) && preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $handle), 422, 'The image container handle is invalid.');
        if ($container = AssetContainer::find($handle)) {
            $this->assertPublicContainer($container);
            return $container;
        }
        abort_unless($handle === self::DEFAULT_CONTAINER, 409, 'The configured image container does not exist. Create that container or select an existing one.');
        $disk = config('filesystems.disks.'.self::DEFAULT_DISK);
        $expected = self::defaultDisk();
        foreach (['driver', 'root', 'url', 'visibility'] as $key) abort_unless(($disk[$key] ?? null) === $expected[$key],
            409, 'The SocraNext image disk name is already configured differently. Select an existing image container.');
        $this->assertPublicUrl($expected['url']);
        $path = $expected['root'];
        abort_unless(is_dir(public_path()) && is_writable(public_path()), 422, 'The website public directory must be writable for first-time image setup.');
        abort_if(is_link($path) || (file_exists($path) && !is_dir($path)), 409, 'The SocraNext image path is already in use.');
        $owned = $this->store->get('managed_assets');
        $identity = ['container' => $handle, 'disk' => self::DEFAULT_DISK, 'path' => $path];
        abort_if($owned && $owned !== $identity, 409, 'The managed image configuration changed; migrate explicitly.');
        abort_if(is_dir($path) && !$owned, 409, 'The SocraNext image directory already belongs to the website. Select an existing image container.');
        abort_if(is_dir($path) && !is_writable($path), 422, 'The SocraNext image directory is not writable.');
        return null;
    }

    private function prepareAssets(): bool
    {
        if ($this->preflightAssets()) return false;
        $path = self::defaultDisk()['root'];
        $createdDirectory = false;
        if (!is_dir($path)) {
            abort_unless(@mkdir($path, 0755), 422, 'The SocraNext image directory could not be created.');
            $createdDirectory = true;
            chmod($path, 0755);
        }
        try {
            $this->store->put('managed_assets', ['container' => self::DEFAULT_CONTAINER, 'disk' => self::DEFAULT_DISK, 'path' => $path]);
        } catch (\Throwable $error) {
            // Only remove the empty directory this invocation just created.
            if ($createdDirectory && is_dir($path) && count(scandir($path)) === 2) @rmdir($path);
            throw $error;
        }
        $maxKilobytes = (int) ceil(max(1, min(50 * 1024 * 1024, (int) config('socranext.content.asset_max_bytes', 10 * 1024 * 1024))) / 1024);
        $container = AssetContainer::make(self::DEFAULT_CONTAINER)->title('SocraNext images')->disk(self::DEFAULT_DISK)
            ->validationRules(['mimes:jpg,jpeg,png,webp,gif,avif', 'max:'.$maxKilobytes]);
        abort_unless($container->save(), 422, 'The SocraNext image container could not be created. Retry setup.');
        $this->assertPublicContainer($container);
        return true;
    }

    public static function assertPublicContainer($container): void
    {
        $disk = $container->diskHandle();
        abort_unless(is_string($disk) && is_array(config('filesystems.disks.'.$disk)), 422, 'The selected image container has no configured disk.');
        $configuration = config('filesystems.disks.'.$disk);
        // Flysystem's local adapter creates a missing root when initialized. Inspect
        // configuration before private()/absoluteUrl() so readiness stays read-only.
        if (($configuration['driver'] ?? null) === 'local') {
            $path = $configuration['root'] ?? null;
            abort_unless(is_string($path) && is_dir($path) && is_writable($path),
                422, 'The selected local image directory must exist and be writable.');
        }
        abort_if($container->private(), 422, 'The selected image container must provide public image URLs.');
        self::assertPublicUrl($container->absoluteUrl());
    }

    private static function assertPublicUrl(?string $url): void
    {
        $parts = $url ? parse_url($url) : false;
        abort_unless(is_array($parts) && ($parts['scheme'] ?? '') === 'https' && !empty($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment']),
            422, 'The image container must have a public HTTPS URL without credentials.');
    }
}
