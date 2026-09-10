<?php

namespace SocraNext\Statamic\Tests;

use Illuminate\Support\Facades\{Http, Route};
use SocraNext\Statamic\Content\ContentRepository;
use SocraNext\Statamic\Support\{Connection, StateStore};
use Statamic\Facades\{Collection, Entry, User};
use Statamic\Http\Responses\DataResponse;

/** The normal CP -> API -> native frontend flow, without mocked readiness or renderers. */
class AutomaticConnectionFlowTest extends TestCase
{
    use \Statamic\Testing\Concerns\FakesRoles;

    private function connectWebsite(): void
    {
        Http::fake(function ($request) {
            $this->assertSame('https://backend.socranext.ai/connect-site', $request->url());
            $this->assertTrue(app(Connection::class)->receive($request['state'], str_repeat('e', 64)));
            return Http::response(['success' => true]);
        });
        $admin = User::make()->id('automatic-admin')->email('automatic@example.test')->set('super', true);
        $this->actingAs($admin)->post('/cp/socranext/connect')->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/cp/socranext')->assertOk()->assertViewHas('ready', true)->assertViewHas('automatic', true)
            ->assertDontSee('name="frontend_ready"', false);
        $this->assertFalse(app(StateStore::class)->get('frontend_ready', false));
        $this->withHeader('x-socranext-token', str_repeat('e', 64));
        $this->getJson('/api/socranext/v1/status')->assertOk()->assertJsonPath('frontend_ready', true);
    }

    private function nativeRoute(string $path, $entry): void
    {
        $routes = new \Illuminate\Routing\RouteCollection;
        foreach (Route::getRoutes() as $route) if ($route->getName() !== 'statamic.site') $routes->add($route);
        Route::setRoutes($routes);
        Route::get($path, fn () => (new DataResponse($entry))->toResponse(request()));
    }

    public function test_connect_then_enable_page_faq_needs_no_install_command_template_tag_or_checkbox(): void
    {
        $this->connectWebsite();
        $dir = $this->temporaryDirectory.'/page-views';
        mkdir($dir);
        $layout = '<html><head><title>Customer page</title></head><body><header>Customer navigation</header><main>{{ template_content }}</main><footer>Customer footer</footer></body></html>';
        file_put_contents($dir.'/layout.antlers.html', $layout);
        file_put_contents($dir.'/page.antlers.html', '<p>Original customer content.</p>');
        view()->addNamespace('flow', $dir);
        Collection::make('pages')->routes('/{slug}')->template('flow::page')->layout('flow::layout')->save();
        $page = Entry::make()->collection('pages')->locale('default')->slug('flow-page')->published(true)->data(['title' => 'Customer page']);
        $page->save();
        $id = app(ContentRepository::class)->identity($page);
        $this->postJson('/api/socranext/v1/store-aio-information', ['page_id' => $id, 'questions' => [
            ['question' => 'Automatically connected?', 'answer' => '<p>Yes, through SocraNext.</p>'],
        ]])->assertOk();
        $this->postJson('/api/socranext/v1/toggle', ['page_id' => $id, 'enabled' => true])->assertOk();
        $this->nativeRoute('/flow-page', $page);
        $html = $this->get('/flow-page')->assertOk()->assertSee('Original customer content.')->assertSee('Automatically connected?')->getContent();
        $this->assertSame(1, substr_count($html, 'id="socranext-frontend-block-'.$id.'"'));
        $this->assertStringContainsString('FAQPage', $html);
        $this->assertStringContainsString('<header>Customer navigation</header><main><p>Original customer content.</p>', $html);
        $this->assertStringContainsString('</main><footer>Customer footer</footer>', $html);
        $this->assertSame($layout, file_get_contents($dir.'/layout.antlers.html'));
    }

    public function test_native_site_shell_wraps_the_unchanged_socranext_article_template_and_styling(): void
    {
        $dir = $this->temporaryDirectory.'/brand-views';
        mkdir($dir.'/layouts', 0755, true);
        $layout = '<html><head><title>Site title</title></head><body><header>Brand navigation</header><main>{{ template_content }}</main><footer>Brand footer</footer></body></html>';
        file_put_contents($dir.'/layouts/brand.antlers.html', $layout);
        view()->getFinder()->prependLocation($dir);
        config(['statamic.system.layout' => 'brand']);
        $this->connectWebsite();
        $this->assertSame('brand', Collection::find('socranext_articles')->layout());
        $this->assertSame('socranext::public.entry', Collection::find('socranext_articles')->template());
        $this->postJson('/api/socranext/v1/store-blog-custom', ['layout' => 'v2', 'custom_css' => '.sn-article{--sn-test-accent:#6d28d9}'])->assertOk();
        $article = $this->postJson('/api/socranext/v1/blog', ['blogId' => 'automatic-flow-article', 'titel' => 'SocraNext article',
            'tekst' => '<p>Our article body.</p>', 'slug' => 'automatic-article', 'titleTag' => 'SocraNext search title'])->assertOk()->json();
        $entry = Entry::find($article['native_id']);
        $this->nativeRoute('/automatic-article', $entry);
        $html = $this->get('/automatic-article')->assertOk()->getContent();
        $this->assertStringContainsString('<header>Brand navigation</header><main>', $html);
        $this->assertStringContainsString('sn-layout-v2', $html);
        $this->assertStringContainsString('--sn-test-accent:#6d28d9', $html);
        $this->assertStringContainsString('<p>Our article body.</p>', $html);
        $this->assertStringContainsString('</main><footer>Brand footer</footer>', $html);
        $this->assertStringContainsString('<title>SocraNext search title</title>', $html);
        $this->assertSame($layout, file_get_contents($dir.'/layouts/brand.antlers.html'));
    }
}
