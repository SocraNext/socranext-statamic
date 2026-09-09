<?php

namespace SocraNext\Statamic\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use SocraNext\Statamic\ServiceProvider;

class AssetPublishingTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app->usePublicPath($this->temporaryDirectory.'/public');
    }

    public function test_brand_assets_publish_without_overwriting_host_configuration(): void
    {
        $public = $this->temporaryDirectory.'/public';
        $hostConfig = config_path('statamic/socranext-asset-publish-sentinel.php');
        (new Filesystem)->ensureDirectoryExists(dirname($hostConfig));
        file_put_contents($hostConfig, "<?php return ['customer_setting' => true];\n");
        try {
            $this->artisan('vendor:publish', ['--tag' => 'socranext-assets', '--force' => true])->assertSuccessful();
            $this->assertFileExists($public.'/vendor/socranext/brand/socranext-logo.svg');
            $this->assertFileExists($public.'/vendor/socranext/fonts/poppins-600.ttf');
            $this->assertFileExists($public.'/vendor/socranext/fonts/montserrat-OFL.txt');
            $this->assertSame("<?php return ['customer_setting' => true];\n", file_get_contents($hostConfig));
            $this->assertSame([], LaravelServiceProvider::pathsToPublish(ServiceProvider::class, 'statamic'));
            foreach (LaravelServiceProvider::pathsToPublish(ServiceProvider::class, 'socranext-assets') as $target) {
                $this->assertStringStartsWith($public.'/vendor/socranext', $target);
            }
        } finally {
            @unlink($hostConfig);
        }
    }
}
