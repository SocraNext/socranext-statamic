<?php

namespace SocraNext\Statamic\Tests;

use Illuminate\Support\Facades\{Event, Route};
use SocraNext\Statamic\Content\ContentRepository;
use SocraNext\Statamic\Rendering\{AutomaticFrontend, Renderer};
use SocraNext\Statamic\Support\{Readiness, StateStore};
use Statamic\Events\ResponseCreated;
use Statamic\Facades\{Collection, Entry, Site, Taxonomy, Term};
use Statamic\Http\Responses\DataResponse;

class AutomaticFrontendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Dedicated fixture routes must precede Statamic's installed catch-all route.
        $routes = new \Illuminate\Routing\RouteCollection;
        foreach (Route::getRoutes() as $route) {
            if ($route->getName() !== 'statamic.site') $routes->add($route);
        }
        Route::setRoutes($routes);
        config(['socranext.frontend.mode' => 'automatic']);
        // Setup readiness has its own integration tests; exercise native output independently.
        $this->mock(Readiness::class, fn ($mock) => $mock->shouldReceive('ready')->andReturn(true));
        app(StateStore::class)->put('frontend_ready', true);
        Event::listen(ResponseCreated::class, AutomaticFrontend::class);
    }

    private function page(string $collection = 'pages')
    {
        Collection::make($collection)->routes('/{slug}')->save();
        $page = Entry::make()->collection($collection)->locale('default')->slug('automatic-page')->published(true)
            ->data(['title' => 'Native page', 'content' => '<p>Original article</p>']);
        $page->save();
        return $page;
    }

    private function faq($resource, string $question = 'A real question?'): int
    {
        $id = app(ContentRepository::class)->identity($resource);
        app(StateStore::class)->transaction(function (array &$state) use ($id, $question) {
            $state['faqs'][$id] = ['enabled' => true, 'questions' => [['question' => $question, 'answer' => '<p>A complete answer.</p>']]];
        });
        return $id;
    }

    private function response($resource, string $html, array $headers = [], string $path = '/automatic-test', int $status = 200, string $method = 'GET')
    {
        app()->instance('request', \Illuminate\Http\Request::create('https://example.com'.$path, $method));
        $response = response($html, $status, ['Content-Type' => 'text/html; charset=UTF-8', ...$headers]);
        ResponseCreated::dispatch($response, $resource);
        return \Illuminate\Testing\TestResponse::fromBaseResponse($response);
    }

    public function test_real_native_response_adds_faq_without_template_tags_and_keeps_customer_layout_bytes(): void
    {
        $page = $this->page();
        $id = $this->faq($page);
        $dir = $this->temporaryDirectory.'/automatic-views';
        mkdir($dir);
        $layout = '<!doctype html><html><head><title>Native title</title></head><body x-data="{ open: false }"><header>Theme</header><main>{{ template_content }}</main><footer>Footer</footer></body></html>';
        file_put_contents($dir.'/layout.antlers.html', $layout);
        file_put_contents($dir.'/page.antlers.html', '<p>Native page without SocraNext tags</p>');
        view()->addNamespace('automatic', $dir);
        $page->set('template', 'automatic::page')->set('layout', 'automatic::layout')->save();
        Route::get('/native-automatic', fn () => (new DataResponse($page))->toResponse(request()));
        $result = $this->get('/native-automatic')->assertOk()->getContent();
        $faq = app(Renderer::class)->faq($id);
        $this->assertSame(str_replace('{{ template_content }}', '<p>Native page without SocraNext tags</p>'.$faq, $layout), $result);
        $this->assertSame(1, substr_count($result, 'id="socranext-frontend-block-'.$id.'"'));
        $this->assertSame('Native page', Entry::find($page->id())->get('title'));
    }

    public function test_existing_managed_article_faq_and_explicit_tag_are_not_duplicated(): void
    {
        $page = $this->page('socranext_articles');
        $page->set('socranext_owned', true)->save();
        $id = $this->faq($page);
        foreach ([app(Renderer::class)->article($page), app(Renderer::class)->faq($id)] as $index => $content) {
            $original = '<html><head></head><body><main>'.$content.'</main></body></html>';
            $result = $this->response($page, $original, path: '/dedupe-'.$index)->getContent();
            $this->assertSame(1, substr_count($result, 'id="socranext-frontend-block-'.$id.'"'));
            $this->assertStringContainsString('<main>'.$content.'</main>', $result);
        }
    }

    public function test_disabled_or_offboarded_faq_and_unmanaged_metadata_leave_site_unchanged(): void
    {
        $page = $this->page();
        $id = $this->faq($page);
        $html = '<html><head><title>Customer SEO</title><script type="application/ld+json">{"@type":"Organization"}</script></head><body><main>Original content</main></body></html>';
        app(StateStore::class)->put('faqs', [$id => ['enabled' => false, 'questions' => [['question' => 'Hidden?', 'answer' => 'Hidden.']]]]);
        $this->assertSame($html, $this->response($page, $html, path: '/disabled')->getContent());
        app(StateStore::class)->put('faqs', []);
        $this->assertSame($html, $this->response($page, $html, path: '/offboarded')->getContent());
    }

    public function test_explicit_metadata_updates_only_head_targets_preserves_schema_and_is_idempotent(): void
    {
        $page = $this->page();
        $id = app(ContentRepository::class)->identity($page);
        $schema = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => 'Desired headline'];
        app(StateStore::class)->put('metadata', [$id => ['title_tag' => 'Desired & safe', 'meta_description' => 'New description', 'json_ld' => $schema]]);
        $unrelated = '<script type="application/ld+json">{ "@type": "Organization", "name": "Customer" }</script>';
        $script = '<script nonce="customer">const fake="<title>Leave this alone</title><meta name=description content=fake></head>";</script>';
        $body = '<body><main><h1>Original title</h1><p>Original article.</p></main></body>';
        $html = '<html><head><title>Old</title><meta NAME="description" content="Old"><link href="https://old.example" REL="canonical">'.$unrelated.$script.'</head>'.$body.'</html>';
        $result = $this->response($page, $html, path: '/metadata-1')->getContent();
        $this->assertStringContainsString('<title>Desired &amp; safe</title>', $result);
        $this->assertStringContainsString('<meta name="description" content="New description">', $result);
        $this->assertStringContainsString('<link rel="canonical" href="'.e($page->absoluteUrl()).'">', $result);
        $this->assertStringContainsString($unrelated.$script, $result);
        $this->assertStringContainsString($body, $result);
        $this->assertSame(1, substr_count($result, 'Desired headline'));
        $this->assertSame($result, $this->response($page, $result, path: '/metadata-2')->getContent());
    }

    public function test_manual_mode_and_unsafe_responses_remain_byte_identical(): void
    {
        $page = $this->page();
        $this->faq($page);
        $html = '<html><head></head><body><main>Original</main></body></html>';
        config(['socranext.frontend.mode' => 'manual']);
        $this->assertSame($html, $this->response($page, $html, path: '/manual')->getContent());
        config(['socranext.frontend.mode' => 'automatic']);
        foreach ([
            ['/api/automatic-test', [], 200], ['/cp/automatic-test', [], 200], ['/socranext/preview/automatic-test', [], 200],
            ['/!/automatic-test', [], 200], ['/not-found', [], 404], ['/feed', ['Content-Type' => 'application/xml'], 200],
            ['/draft-header', ['X-Statamic-Draft' => 'true'], 200], ['/private-header', ['X-Statamic-Private' => 'true'], 200],
            ['/protected-header', ['X-Statamic-Protected' => 'true'], 200],
        ] as [$path, $headers, $status]) {
            $this->assertSame($html, $this->response($page, $html, $headers, $path, $status)->getContent(), $path);
        }
        $this->assertSame($html, $this->response($page, $html, path: '/posted', method: 'POST')->getContent());
        $this->assertSame($html, $this->response($page, $html, path: '/head', method: 'HEAD')->getContent());
        $this->assertSame($html, $this->response($page, $html, path: '/preview?token=statamic-preview')->getContent());
        $this->assertSame($html, $this->response($page, $html, path: '/preview?socranext_token=signed-preview')->getContent());
        $page->published(false)->save();
        $this->assertSame($html, $this->response($page, $html, path: '/draft-resource')->getContent());
        $page->published(true)->set('protect', 'password')->save();
        $this->assertSame($html, $this->response($page, $html, path: '/protected-resource')->getContent());
    }

    public function test_localized_term_faq_uses_event_resource_locale_not_global_default(): void
    {
        Site::setSites(['default' => ['name' => 'Dutch', 'locale' => 'nl_NL', 'url' => '/'], 'english' => ['name' => 'English', 'locale' => 'en_GB', 'url' => '/en/']]);
        config(['statamic.system.multisite' => true, 'socranext.content.sites' => ['default', 'english']]);
        Taxonomy::make('topics')->sites(['default', 'english'])->save();
        $term = Term::make()->taxonomy('topics')->slug('automatic-topic')->data(['title' => 'Topic']);
        $term->save();
        $term->in('english')->data(['title' => 'English topic'])->save();
        $this->faq($term->in('default'), 'Nederlandse vraag?');
        $englishId = $this->faq($term->in('english'), 'English question?');
        app(StateStore::class)->put('styles', ['faq' => ['title_text_i18n' => ['nl' => 'Nederlandse FAQ', 'en' => 'English FAQ']]]);
        Site::setCurrent('default');
        $html = '<html><head></head><body><main>Topic</main></body></html>';
        $result = $this->response($term->in('english'), $html, path: '/term-locale')->getContent();
        $this->assertStringContainsString('id="socranext-frontend-block-'.$englishId.'"', $result);
        $this->assertStringContainsString('English question?', $result);
        $this->assertStringContainsString('English FAQ', $result);
        $this->assertStringNotContainsString('Nederlandse vraag?', $result);
    }

    public function test_customer_pages_without_presentation_never_grow_the_identity_registry(): void
    {
        $page = $this->page();
        $other = Entry::make()->collection('pages')->locale('default')->slug('another-page')->published(true)->data(['title' => 'Another']);
        $other->save();
        $this->faq($other);
        $before = file_get_contents(config('socranext.state_path'));
        $html = '<html><head><title>Customer</title></head><body><main>Original</main></body></html>';
        $this->assertSame($html, $this->response($page, $html)->getContent());
        $this->assertSame($before, file_get_contents(config('socranext.state_path')));
    }

    public function test_partial_metadata_edits_preserve_customer_seo_fields_that_were_not_requested(): void
    {
        $page = $this->page();
        $id = app(ContentRepository::class)->identity($page);
        $title = '<title>Customer SEO title</title>';
        $description = '<meta name="description" content="Customer SEO description">';
        $schema = '<script type="application/ld+json">{"@type":"Article","headline":"Customer schema"}</script>';
        $html = '<html><head>'.$title.$description.'<link rel="canonical" href="https://old.example/">'.$schema.'</head><body><main>Original body</main></body></html>';
        foreach ([
            ['title_tag' => 'Only a new title', 'revision' => 'saved'],
            ['meta_description' => 'Only a new description', 'revision' => 'saved'],
            ['revision' => 'saved-after-slug-change'],
        ] as $index => $override) {
            app(StateStore::class)->put('metadata', [$id => $override]);
            $result = $this->response($page, $html, path: '/partial-metadata-'.$index)->getContent();
            $canonical = '<link rel="canonical" href="'.e($page->absoluteUrl()).'">';
            $expectedTitle = isset($override['title_tag']) ? '<title>Only a new title</title>' : $title;
            $expectedDescription = isset($override['meta_description']) ? '<meta name="description" content="Only a new description">' : $description;
            $expected = '<html><head>'.$expectedTitle.$expectedDescription.$canonical.$schema.'</head><body><main>Original body</main></body></html>';
            $this->assertSame($expected, $result);
        }
    }

    public function test_renderer_failure_leaves_native_response_and_headers_untouched_and_logs_no_details(): void
    {
        $page = $this->page();
        $this->faq($page);
        $this->mock(Renderer::class, fn ($mock) => $mock->shouldReceive('faq')->andThrow(new \RuntimeException('Private customer information must not enter the log.')));
        \Illuminate\Support\Facades\Log::shouldReceive('warning')->atLeast()->once()->with('SocraNext automatic frontend was skipped.', ['exception_class' => \RuntimeException::class]);
        $html = '<html><head></head><body><main>Original</main></body></html>';
        $response = $this->response($page, $html, ['ETag' => '"original"', 'Last-Modified' => 'Wed, 09 Sep 2026 10:00:00 GMT']);
        $this->assertSame($html, $response->getContent());
        $response->assertOk()->assertHeader('ETag', '"original"')->assertHeader('Last-Modified', 'Wed, 09 Sep 2026 10:00:00 GMT');
    }

    public function test_updated_output_invalidates_native_representation_headers_and_not_modified_response_is_untouched(): void
    {
        $page = $this->page();
        $this->faq($page);
        $html = '<html><head></head><body><main>Original</main></body></html>';
        $headers = ['ETag' => '"original"', 'Last-Modified' => 'Wed, 09 Sep 2026 10:00:00 GMT', 'Content-Length' => (string) strlen($html)];
        $response = $this->response($page, $html, $headers);
        $response->assertHeaderMissing('ETag')->assertHeaderMissing('Last-Modified')->assertHeaderMissing('Content-Length');
        $this->assertNotSame($html, $response->getContent());
        $unchanged = $this->response($page, $html, $headers, '/not-modified', 304);
        $this->assertSame($html, $unchanged->getContent());
        $unchanged->assertHeader('ETag', '"original"');
    }

    public function test_corrupt_connector_state_cannot_break_the_native_customer_page(): void
    {
        $page = $this->page();
        file_put_contents(config('socranext.state_path'), '{invalid state');
        \Illuminate\Support\Facades\Log::shouldReceive('warning')->atLeast()->once()->with('SocraNext automatic frontend was skipped.', ['exception_class' => \JsonException::class]);
        $html = '<html><head></head><body><main>Customer content remains available.</main></body></html>';
        $response = $this->response($page, $html);
        $response->assertOk();
        $this->assertSame($html, $response->getContent());
        $this->assertSame('{invalid state', file_get_contents(config('socranext.state_path')));
    }
}
