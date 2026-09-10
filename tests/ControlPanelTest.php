<?php

namespace SocraNext\Statamic\Tests;

use Illuminate\Support\Facades\Http;
use SocraNext\Statamic\Content\ArticlePublisher;
use SocraNext\Statamic\Support\{Connection, StateStore};
use Statamic\Facades\{AssetContainer, Collection, Site, Taxonomy, User};

class ControlPanelTest extends TestCase
{
    use \Statamic\Testing\Concerns\FakesRoles;

    private function admin(string $locale = 'en')
    {
        return User::make()->id('cp-admin')->email('cp-admin@example.test')->set('super', true)->setPreference('locale', $locale);
    }

    private function configureWebsite(): void
    {
        $this->configureAssets();
        app(ArticlePublisher::class)->prepare();
    }

    private function configureAssets(): void
    {
        mkdir($this->temporaryDirectory.'/assets', 0755);
        config(['filesystems.disks.cp_test_assets' => ['driver' => 'local', 'root' => $this->temporaryDirectory.'/assets', 'url' => 'https://example.com/assets']]);
        AssetContainer::make(config('socranext.content.asset_container'))->disk('cp_test_assets')->save();
    }

    private function connected(): void
    {
        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('d', 64));
    }

    private function dom(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return new \DOMXPath($document);
    }

    public function test_native_shell_and_configuration_checks_render_without_prepared_resources(): void
    {
        $response = $this->actingAs($this->admin())->get('/cp/socranext')->assertOk()
            ->assertViewIs('socranext::cp.index')->assertViewHas('connected', false)->assertViewHas('ready', false)
            ->assertViewHas('setupComplete', false)->assertViewHas('setupChecks', [
                'website_address' => true, 'article_collection' => false, 'image_storage' => false, 'languages' => true,
                'templates' => false, 'native_routes' => true,
            ])->assertSeeText('Connect with SocraNext')->assertSeeText('Connecting sets up your articles, images and FAQ placement for you.')
            ->assertDontSee('name="frontend_ready"', false);
        $dom = $this->dom($response->getContent());
        $this->assertSame(1, $dom->query('//*[@id="statamic" and @data-page]')->length);
        $this->assertSame('en', $dom->query('/html')->item(0)->getAttribute('lang'));
        $this->assertSame(0, $dom->query('//ol[contains(@class,"sncp-steps")]')->length);
        $forms = $dom->query('//form[translate(@method,"POST","post")="post"]');
        $this->assertSame(1, $forms->length);
        foreach ($forms as $form) {
            $tokens = $dom->query('.//input[@name="_token" and @type="hidden"]', $form);
            $this->assertSame(1, $tokens->length);
            $this->assertNotSame('', $tokens->item(0)->getAttribute('value'));
        }
    }

    public function test_prepared_website_is_ready_automatically_without_confirmation(): void
    {
        $this->configureWebsite();
        $this->connected();
        $this->actingAs($this->admin())->get('/cp/socranext')->assertOk()
            ->assertViewHas('connected', true)->assertViewHas('ready', true)->assertViewHas('setupComplete', true)
            ->assertSeeText('Grow your visibility in AI.')->assertSeeText('Ready to use')->assertSeeText('Open SocraNext')
            ->assertDontSee('name="frontend_ready"', false);
        $this->assertFalse(app(StateStore::class)->get('frontend_ready', false));
        $this->post('/cp/socranext/readiness', ['frontend_ready' => 1])->assertStatus(409);
        $response = $this->get('/cp/socranext')->assertOk()->assertViewHas('connected', true)->assertViewHas('ready', true);
        $this->assertGreaterThan(0, $this->dom($response->getContent())->query('//*[not(self::script) and normalize-space(text())="Ready to use"]')->length);
        $this->post('/cp/socranext/disconnect')->assertRedirect();
        $this->get('/cp/socranext')->assertOk()->assertViewHas('connected', false)->assertViewHas('ready', true)
            ->assertSeeText('Connect with SocraNext');
    }

    public function test_existing_customer_handles_are_not_reported_as_a_completed_installation(): void
    {
        $this->configureAssets();
        $collection = config('socranext.content.managed_collection');
        $taxonomy = config('socranext.content.managed_taxonomy');
        Collection::make($collection)->title('Customer articles')->save();
        Taxonomy::make($taxonomy)->title('Customer categories')->save();
        $this->actingAs($this->admin())->get('/cp/socranext')->assertOk()
            ->assertViewHas('setupComplete', false)
            ->assertViewHas('setupChecks', ['website_address' => true, 'article_collection' => false,
                'image_storage' => true, 'languages' => true, 'templates' => false, 'native_routes' => true]);
        // The visible status must agree with the real installer rejection,
        // while the pre-existing customer's resources remain untouched.
        try {
            app(ArticlePublisher::class)->prepare();
            $this->fail('Customer-owned handles must not be adopted.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $this->assertSame('Customer articles', Collection::find($collection)->title());
        $this->assertSame('Customer categories', Taxonomy::find($taxonomy)->title());
        $this->assertSame([], app(StateStore::class)->get('managed_resources', []));
    }

    public function test_changing_the_taxonomy_handle_requires_configuration_review(): void
    {
        $this->configureWebsite();
        Taxonomy::make('other_categories')->save();
        config(['socranext.content.managed_taxonomy' => 'other_categories']);
        $this->actingAs($this->admin())->get('/cp/socranext')->assertOk()
            ->assertViewHas('setupComplete', false)
            ->assertViewHas('setupChecks', fn ($checks) => $checks['article_collection'] === false);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Managed taxonomy configuration changed');
        app(ArticlePublisher::class)->prepare();
    }

    public function test_incomplete_resource_configuration_still_renders_the_setup_screen(): void
    {
        config(['socranext.content.managed_collection' => null,
            'socranext.content.managed_taxonomy' => null, 'socranext.content.asset_container' => null]);
        $this->actingAs($this->admin())->get('/cp/socranext')->assertOk()
            ->assertViewHas('setupComplete', false)
            ->assertViewHas('setupChecks', fn ($checks) => !$checks['article_collection'] && !$checks['image_storage']);
    }

    public function test_invalid_multisite_does_not_show_ready_despite_previously_saved_review(): void
    {
        $this->configureWebsite();
        $this->connected();
        app(StateStore::class)->put('frontend_ready', true);
        Site::setSites([
            'default' => ['name' => 'English', 'locale' => 'en_US', 'url' => 'https://example.com/'],
            'nl' => ['name' => 'Dutch', 'locale' => 'nl_NL', 'url' => 'https://example.com/nl/'],
        ]);
        config(['statamic.system.multisite' => false, 'socranext.content.sites' => ['default', 'nl']]);
        $response = $this->actingAs($this->admin())->get('/cp/socranext')->assertOk()
            ->assertViewHas('connected', true)->assertViewHas('frontendChecked', true)->assertViewHas('ready', false)
            ->assertViewHas('setupComplete', false)->assertViewHas('siteNames', [])
            ->assertViewHas('setupChecks', fn ($checks) => $checks['languages'] === false)
            ->assertSeeText('Setup needs attention');
        $this->assertSame(0, $this->dom($response->getContent())->query('//*[not(self::script) and normalize-space(text())="Ready to use"]')->length);
    }

    public function test_native_user_locale_selects_dutch_screen_and_flash_messages(): void
    {
        config(['app.locale' => 'en']);
        $response = $this->actingAs($this->admin('nl'))->get('/cp/socranext')->assertOk()
            ->assertSeeText('Verbinden met SocraNext')->assertSeeText('Installatiedetails')
            ->assertSeeText('Gratis koppeling');
        $dom = $this->dom($response->getContent());
        $this->assertSame('nl', $dom->query('/html')->item(0)->getAttribute('lang'));
        $visibleText = '';
        foreach ($dom->query('//body//*[not(self::script or self::style)]/text()') as $node) $visibleText .= $node->textContent;
        // Native Statamic serializes translation keys for JS too; only visible labels must be translated.
        $this->assertStringNotContainsString('socranext::cp.', $visibleText);
        config(['socranext.frontend.mode' => 'manual']);
        $this->post('/cp/socranext/readiness', ['frontend_ready' => 0])->assertRedirect()
            ->assertSessionHas('socranext_message', 'De websitestatus is opgeslagen.');
    }

    public function test_connection_credentials_and_pending_state_are_never_rendered(): void
    {
        $connection = app(Connection::class);
        $token = str_repeat('a7', 32);
        $connection->receive($connection->begin(), $token);
        $pending = $connection->begin();
        $store = app(StateStore::class);
        $response = $this->actingAs($this->admin())->get('/cp/socranext')->assertOk();
        foreach ([$token, $pending, $store->get('token_hash'), $store->get('connection_epoch'), $store->get('connect_state')['hash']] as $secret) {
            $this->assertIsString($secret);
            $this->assertNotSame('', $secret);
            $response->assertDontSee($secret, false);
        }
    }

    public function test_configure_permission_protects_every_control_panel_mutation(): void
    {
        $this->connected();
        $this->setTestRoles(['editor' => ['access cp']]);
        $editor = User::make()->id('cp-editor')->email('cp-editor@example.test')->assignRole('editor');
        Http::preventStrayRequests();
        foreach (['connect', 'disconnect', 'readiness'] as $action) {
            $this->actingAs($editor)->postJson('/cp/socranext/'.$action, ['frontend_ready' => 1])->assertForbidden();
        }
        $this->assertTrue(app(Connection::class)->connected());
        $this->assertFalse(app(StateStore::class)->get('frontend_ready', false));
        Http::assertNothingSent();
    }

    public function test_control_panel_posts_still_require_csrf_when_not_running_in_test_bypass(): void
    {
        config(['socranext.frontend.mode' => 'manual']);
        $this->actingAs($this->admin());
        $environment = app()->environment();
        app()->instance('env', 'local');
        try {
            foreach (['connect', 'disconnect', 'readiness'] as $action) {
                $this->post('/cp/socranext/'.$action, ['frontend_ready' => 1])->assertStatus(419);
            }
            $this->withSession(['_token' => 'test-session-csrf-token'])->post('/cp/socranext/readiness', [
                '_token' => 'test-session-csrf-token', 'frontend_ready' => 1,
            ])->assertRedirect();
            $this->assertTrue(app(StateStore::class)->get('frontend_ready'));
        } finally {
            app()->instance('env', $environment);
        }
    }
}
