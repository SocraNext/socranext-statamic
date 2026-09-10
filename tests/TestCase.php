<?php

namespace SocraNext\Statamic\Tests;

use Illuminate\Filesystem\Filesystem;
use SocraNext\Statamic\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
    protected string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        // Testbench applies this fixture's URL/public path after providers register.
        $disks = config('filesystems.disks', []);
        unset($disks['socranext_public']);
        config(['filesystems.disks' => $disks]);
        \SocraNext\Statamic\Support\Setup::registerDefaultDisk();
        \Statamic\Facades\Blueprint::setDirectory($this->temporaryDirectory.'/blueprints');
        // Match the real Laravel host's global input normalizers for every HTTP test.
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->pushMiddleware(\Illuminate\Foundation\Http\Middleware\TrimStrings::class);
        $kernel->pushMiddleware(\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $this->temporaryDirectory = sys_get_temp_dir().'/socranext-test-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0700, true);
        mkdir($this->temporaryDirectory.'/public', 0755);
        $app->usePublicPath($this->temporaryDirectory.'/public');
        $app['config']->set('socranext.state_path', $this->temporaryDirectory.'/state.json');
        $app['config']->set('socranext.site_url', 'https://example.com');
        $app['config']->set('app.url', 'https://example.com');
        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.stache.watcher', false);
        foreach ($app['config']->get('statamic.stache.stores', []) as $key => $value) {
            if (isset($value['directory'])) $app['config']->set("statamic.stache.stores.{$key}.directory", $this->temporaryDirectory.'/content/'.$key);
        }
        $app['config']->set('statamic.revisions.path', $this->temporaryDirectory.'/revisions');
    }

    protected function tearDown(): void
    {
        try { parent::tearDown(); } finally { if (isset($this->temporaryDirectory)) (new Filesystem)->deleteDirectory($this->temporaryDirectory); }
    }
}
