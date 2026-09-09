<?php

namespace SocraNext\Statamic\Tests;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use SocraNext\Statamic\ServiceProvider;

class TranslationTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app->useLangPath($this->temporaryDirectory.'/lang');
    }

    public function test_native_messages_and_addon_labels_keep_their_own_translations(): void
    {
        $nativeLang = dirname((new \ReflectionClass(\Statamic\Statamic::class))->getFileName(), 2).'/lang';
        foreach (['nl', 'en'] as $locale) {
            $native = require $nativeLang.'/'.$locale.'/messages.php';
            foreach (['getting_started_widget_header', 'getting_started_widget_intro', 'getting_started_widget_docs',
                'blueprints_intro', 'getting_started_widget_collections', 'getting_started_widget_navigation',
                'licensing_trial_mode_alert_statamic'] as $key) {
                $this->assertSame($native[$key], __('statamic::messages.'.$key, [], $locale));
            }
        }
        $this->assertSame('Verbinden met SocraNext', __('socranext::cp.connect', [], 'nl'));
        $this->assertSame('Connect with SocraNext', __('socranext::cp.connect', [], 'en'));
        $this->assertSame([], LaravelServiceProvider::pathsToPublish(ServiceProvider::class, 'statamic-translations'));
    }

    public function test_customer_native_translation_overrides_continue_to_work(): void
    {
        $directory = lang_path('vendor/statamic/nl');
        mkdir($directory, 0700, true);
        file_put_contents($directory.'/messages.php', "<?php return ['getting_started_widget_header' => 'Mijn website beheren'];\n");

        $this->assertSame('Mijn website beheren', __('statamic::messages.getting_started_widget_header', [], 'nl'));
        $this->assertSame('Getting Started with Statamic', __('statamic::messages.getting_started_widget_header', [], 'en'));
        $this->assertSame('Verbinden met SocraNext', __('socranext::cp.connect', [], 'nl'));
    }
}
