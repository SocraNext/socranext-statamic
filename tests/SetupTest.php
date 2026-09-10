<?php

namespace SocraNext\Statamic\Tests;

use Illuminate\Support\Facades\Event;
use SocraNext\Statamic\Content\AssetImporter;
use SocraNext\Statamic\Support\{Setup, StateStore};
use Statamic\Events\AssetContainerSaving;
use Statamic\Facades\{Asset, AssetContainer, Blueprint, Collection, Entry, Taxonomy};
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SetupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Blueprint::setDirectory($this->temporaryDirectory.'/blueprints');
    }

    private function files(): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporaryDirectory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) $files[$file->getPathname()] = file_get_contents($file->getPathname());
        }
        ksort($files);
        return $files;
    }

    private function rejected(int $status): void
    {
        try { app(Setup::class)->prepare(); $this->fail('Conflicting setup was accepted.'); }
        catch (HttpExceptionInterface $error) { $this->assertSame($status, $error->getStatusCode()); }
    }

    private function assertNotInstalled(): void
    {
        $this->assertNull(Collection::find('socranext_articles'));
        $this->assertNull(Collection::find('socranext_archives'));
        $this->assertNull(Taxonomy::find('socranext_categories'));
        $this->assertNull(app(StateStore::class)->get('managed_resources'));
        $this->assertFalse(app(Setup::class)->isComplete());
    }

    public function test_disk_registration_changes_no_files_and_preserves_explicit_disk_configuration(): void
    {
        $before = $this->files();
        Setup::registerDefaultDisk();
        $this->assertSame($before, $this->files());
        $this->assertDirectoryDoesNotExist(public_path('socranext-assets'));
        $this->assertSame(public_path('socranext-assets'), config('filesystems.disks.socranext_public.root'));
        $this->assertSame('https://example.com/socranext-assets', config('filesystems.disks.socranext_public.url'));
        $custom = ['driver' => 'local', 'root' => '/customer-controlled', 'url' => 'https://cdn.example/images'];
        config(['filesystems.disks.socranext_public' => $custom]);
        Setup::registerDefaultDisk();
        $this->assertSame($custom, config('filesystems.disks.socranext_public'));
        $this->assertSame($before, $this->files());
    }

    public function test_first_setup_creates_public_image_storage_and_repeat_preserves_all_native_bytes(): void
    {
        Collection::make('pages')->routes('/{slug}')->save();
        $customer = Entry::make()->id('customer')->collection('pages')->slug('home')->data(['title' => 'Customer content']);
        $customer->save();
        $customerBytes = file_get_contents($customer->path());
        $result = app(Setup::class)->prepare();
        $this->assertTrue($result['asset_container_created']);
        $this->assertTrue(app(Setup::class)->isComplete());
        $container = AssetContainer::find('socranext');
        $this->assertSame('socranext_public', $container->diskHandle());
        $this->assertFalse($container->private());
        $this->assertSame('https://example.com/socranext-assets', rtrim($container->absoluteUrl(), '/'));
        $this->assertSame(['mimes:jpg,jpeg,png,webp,gif,avif', 'max:10240'], $container->validationRules());
        $this->assertSame('socranext::public.layout', Collection::find('socranext_articles')->layout());
        $this->assertSame($customerBytes, file_get_contents($customer->path()));

        $importer = new class extends AssetImporter {
            protected function download(string $url): array
            {
                $file = tempnam(sys_get_temp_dir(), 'socranext-setup-image-');
                imagepng(imagecreatetruecolor(2, 2), $file);
                return [$file, 'image/png'];
            }
        };
        $id = $importer->import(['featuredImageUrl' => 'https://images.example/photo.png', 'featuredImageAlt' => 'Original image alt']);
        $this->assertTrue(Asset::find($id)->exists());
        $this->assertStringStartsWith('https://example.com/socranext-assets/socranext/', Asset::find($id)->absoluteUrl());
        $files = $this->files();
        $this->assertFalse(app(Setup::class)->prepare()['asset_container_created']);
        $this->assertSame($files, $this->files());
        $this->assertSame('Original image alt', Asset::find($id)->get('alt'));
        $this->assertSame(1, Entry::query()->where('collection', 'socranext_archives')->count());
    }

    public function test_existing_selected_public_container_is_used_without_changing_it_or_its_disk(): void
    {
        $disk = ['driver' => 'local', 'root' => public_path('customer-images'), 'url' => 'https://cdn.example/images', 'visibility' => 'public'];
        mkdir($disk['root']);
        file_put_contents($disk['root'].'/original.txt', 'Customer file');
        config(['socranext.content.asset_container' => 'customer_images', 'filesystems.disks.customer_images' => $disk]);
        $container = AssetContainer::make('customer_images')->title('Customer photos')->disk('customer_images')->validationRules(['max:40000']);
        $container->save();
        $bytes = file_get_contents($container->path());
        $this->assertFalse(app(Setup::class)->prepare()['asset_container_created']);
        $this->assertSame($bytes, file_get_contents($container->path()));
        $this->assertSame($disk, config('filesystems.disks.customer_images'));
        $this->assertSame('Customer file', file_get_contents($disk['root'].'/original.txt'));
        $this->assertDirectoryDoesNotExist(public_path('socranext-assets'));
        $this->assertNull(app(StateStore::class)->get('managed_assets'));
    }

    public function test_missing_custom_container_and_reserved_disk_collision_fail_before_creating_content(): void
    {
        config(['socranext.content.asset_container' => 'missing_customer_container']);
        $this->rejected(409);
        $this->assertNotInstalled();
        config(['socranext.content.asset_container' => 'socranext', 'filesystems.disks.socranext_public.root' => public_path('different')]);
        $this->rejected(409);
        $this->assertNotInstalled();
        $this->assertDirectoryDoesNotExist(public_path('socranext-assets'));
    }

    public function test_existing_local_container_with_missing_root_or_http_url_fails_before_writes(): void
    {
        $path = public_path('customer-images');
        config(['filesystems.disks.customer_images' => [
            'driver' => 'local', 'root' => $path, 'url' => 'https://cdn.example/images', 'visibility' => 'public',
        ]]);
        $container = AssetContainer::make('socranext')->disk('customer_images');
        $container->save();
        $bytes = file_get_contents($container->path());
        $this->assertFalse(app(\SocraNext\Statamic\Support\Readiness::class)->checks()['image_storage']);
        $this->assertDirectoryDoesNotExist($path);
        $this->rejected(422);
        $this->assertNotInstalled();
        $this->assertDirectoryDoesNotExist($path);
        mkdir($path);
        config(['filesystems.disks.customer_images.url' => 'http://cdn.example/images']);
        \Illuminate\Support\Facades\Storage::forgetDisk('customer_images');
        $this->rejected(422);
        $this->assertNotInstalled();
        $this->assertSame($bytes, file_get_contents($container->path()));
        $this->assertDirectoryDoesNotExist(public_path('socranext-assets'));
    }

    public function test_http_default_asset_url_is_rejected_before_any_installation_writes(): void
    {
        config(['socranext.site_url' => 'http://example.com',
            'filesystems.disks.socranext_public.url' => 'http://example.com/socranext-assets']);
        $this->rejected(422);
        $this->assertNotInstalled();
        $this->assertFileDoesNotExist(config('socranext.state_path'));
        $this->assertDirectoryDoesNotExist(public_path('socranext-assets'));
    }

    public function test_existing_unowned_asset_directory_and_symlink_are_never_adopted(): void
    {
        $path = public_path('socranext-assets');
        mkdir($path);
        file_put_contents($path.'/customer.txt', 'Do not change');
        $this->rejected(409);
        $this->assertNotInstalled();
        $this->assertSame('Do not change', file_get_contents($path.'/customer.txt'));
        unlink($path.'/customer.txt');
        $this->rejected(409); // An empty customer directory is still not ours.
        rmdir($path);
        mkdir(public_path('customer-location'));
        symlink(public_path('customer-location'), $path);
        $this->rejected(409);
        $this->assertNotInstalled();
        $this->assertSame(public_path('customer-location'), readlink($path));
    }

    public function test_resource_and_archive_url_collisions_leave_customer_data_untouched(): void
    {
        Collection::make('pages')->routes('/{slug}')->save();
        $customer = Entry::make()->id('customer-archive')->collection('pages')->slug('artikelen-sn')->data(['title' => 'Existing landing page']);
        $customer->save();
        $bytes = file_get_contents($customer->path());
        $this->rejected(409);
        $this->assertNotInstalled();
        $this->assertSame($bytes, file_get_contents($customer->path()));
        $this->assertDirectoryDoesNotExist(public_path('socranext-assets'));
        $customer->slug('customer-archive')->save();
        Collection::make('socranext_articles')->title('Existing customer collection')->save();
        $this->rejected(409);
        $this->assertSame('Existing customer collection', Collection::find('socranext_articles')->title());
        $this->assertNull(Taxonomy::find('socranext_categories'));
        $this->assertNull(app(StateStore::class)->get('managed_resources'));
    }

    public function test_private_image_storage_and_missing_custom_layout_fail_before_writes(): void
    {
        config(['filesystems.disks.private_images' => ['driver' => 'local', 'root' => $this->temporaryDirectory.'/private-images']]);
        AssetContainer::make('socranext')->disk('private_images')->save();
        $this->rejected(422);
        $this->assertNotInstalled();
        config(['socranext.content.article_layout' => 'missing_custom_theme']);
        $this->rejected(422);
        $this->assertNotInstalled();
        $this->assertDirectoryDoesNotExist(public_path('socranext-assets'));
    }

    public function test_completed_setup_never_resurrects_a_removed_archive(): void
    {
        app(Setup::class)->prepare();
        $archive = Entry::query()->where('collection', 'socranext_archives')->first();
        $id = $archive->id();
        $this->assertTrue($archive->delete());
        $before = $this->files();
        $this->rejected(409);
        $this->assertNull(Entry::find($id));
        $this->assertSame($before, $this->files());
    }

    public function test_rejected_container_creation_can_retry_only_its_own_partial_setup(): void
    {
        $reject = true;
        Event::listen(AssetContainerSaving::class, function () use (&$reject) { return $reject ? false : null; });
        $this->rejected(422);
        $this->assertFalse(app(Setup::class)->isComplete());
        $this->assertNull(AssetContainer::find('socranext'));
        $this->assertSame('socranext', app(StateStore::class)->get('managed_assets')['container']);
        $archive = Entry::query()->where('collection', 'socranext_archives')->first()->id();
        $reject = false;
        $this->assertTrue(app(Setup::class)->prepare()['asset_container_created']);
        $this->assertTrue(app(Setup::class)->isComplete());
        $this->assertSame($archive, Entry::query()->where('collection', 'socranext_archives')->first()->id());
    }

    public function test_upgrade_from_legacy_installation_never_resurrects_a_removed_archive(): void
    {
        app(\SocraNext\Statamic\Content\ArticlePublisher::class)->prepare();
        $archive = Entry::query()->where('collection', 'socranext_archives')->first();
        $id = $archive->id();
        $this->assertTrue($archive->delete());
        $before = $this->files();
        $this->rejected(409);
        $this->assertNull(Entry::find($id));
        $this->assertSame($before, $this->files());
        $this->assertFalse(app(Setup::class)->isComplete());
        $this->assertNull(app(StateStore::class)->get('setup_in_progress'));
    }

    public function test_legacy_installation_with_intact_resources_can_prepare_images_without_rewriting_content(): void
    {
        app(\SocraNext\Statamic\Content\ArticlePublisher::class)->prepare();
        $before = array_filter($this->files(), fn ($path) => str_contains($path, '/content/') || str_contains($path, '/blueprints/'), ARRAY_FILTER_USE_KEY);
        $this->assertTrue(app(Setup::class)->prepare()['asset_container_created']);
        $this->assertTrue(app(Setup::class)->isComplete());
        foreach ($before as $path => $bytes) $this->assertSame($bytes, file_get_contents($path));
        $this->assertNull(app(StateStore::class)->get('setup_in_progress'));
    }

    public function test_existing_blade_resources_ignore_unused_layout_configuration_during_upgrade_and_reconnect(): void
    {
        app(\SocraNext\Statamic\Content\ArticlePublisher::class)->prepare();
        $views = $this->temporaryDirectory.'/views';
        mkdir($views);
        file_put_contents($views.'/customer-page.blade.php', '<!doctype html><html><body>Customer layout</body></html>');
        view()->addLocation($views);
        foreach (['socranext_articles', 'socranext_archives'] as $handle) {
            Collection::find($handle)->template('customer-page')->layout(false)->save();
        }
        config(['socranext.content.article_layout' => 'missing_unused_layout']);
        $before = array_filter($this->files(), fn ($path) => str_contains($path, '/content/') || str_contains($path, '/blueprints/'), ARRAY_FILTER_USE_KEY);
        $this->assertTrue(app(Setup::class)->prepare()['asset_container_created']);
        $this->assertTrue(app(\SocraNext\Statamic\Support\Readiness::class)->ready());
        $this->assertFalse(app(Setup::class)->prepare()['asset_container_created']);
        foreach ($before as $path => $bytes) $this->assertSame($bytes, file_get_contents($path));
    }
}
