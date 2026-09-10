<?php

namespace SocraNext\Statamic\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use SocraNext\Statamic\Content\{AssetImporter, ContentRepository, MutationLock};
use SocraNext\Statamic\Rendering\Renderer;
use SocraNext\Statamic\Support\{Connection, StateStore};
use Statamic\Events\EntryDeleting;
use Statamic\Facades\{Asset, AssetContainer, Blueprint, Collection, Entry, Site, StaticCache};
use Statamic\View\View;

class FaqOffboardingTest extends TestCase
{
    private const FAQ_ONLY = ['mode' => 'faq_only', 'articles_preserved' => true];
    private const FULL_PURGE = ['mode' => 'full_purge', 'articles_preserved' => false];

    private function authenticate(): void
    {
        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('f', 64));
        $this->withHeader('x-socranext-token', str_repeat('f', 64));
    }

    private function state(): array
    {
        return json_decode(file_get_contents(config('socranext.state_path')), true, 512, JSON_THROW_ON_ERROR);
    }

    private function preservedState(): array
    {
        $state = $this->state();
        unset($state['faqs'], $state['last_offboarding']);
        return $state;
    }

    private function files(): array
    {
        $files = [];
        foreach (['content', 'blueprints', 'assets', 'revisions', 'views'] as $directory) {
            $path = $this->temporaryDirectory.'/'.$directory;
            if (!is_dir($path)) continue;
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) $files[substr($file->getPathname(), strlen($this->temporaryDirectory))] = file_get_contents($file->getPathname());
            }
        }
        ksort($files);
        return $files;
    }

    private function articles(): array
    {
        Blueprint::setDirectory($this->temporaryDirectory.'/blueprints');
        Site::setSites([
            'default' => ['name' => 'Dutch', 'locale' => 'nl_NL', 'url' => 'https://example.com/'],
            'english' => ['name' => 'English', 'locale' => 'en_GB', 'url' => 'https://example.com/en/'],
        ]);
        config(['socranext.content.sites' => ['default', 'english'], 'statamic.system.multisite' => true, 'statamic.revisions.enabled' => true]);
        config(['filesystems.disks.offboard_test' => ['driver' => 'local', 'root' => $this->temporaryDirectory.'/assets', 'url' => 'https://example.com/assets']]);
        AssetContainer::make('socranext')->disk('offboard_test')->save();
        $this->app->instance(AssetImporter::class, new class extends AssetImporter {
            protected function download(string $url): array
            {
                $file = tempnam(sys_get_temp_dir(), 'socranext-offboard-asset-');
                $image = imagecreatetruecolor(2, 2);
                imagepng($image, $file);
                return [$file, 'image/png'];
            }
        });
        $category = $this->postJson('/api/socranext/v1/blog-category', ['name' => 'Nieuws', 'language' => 'nl'])->assertOk()->json();
        $englishCategory = $this->postJson('/api/socranext/v1/blog-category', ['name' => 'News', 'language' => 'en', 'sourceTermId' => $category['id']])->assertOk()->json();
        $payload = ['blogId' => 'offboard-nl', 'titel' => 'Behouden artikel', 'tekst' => '<h2>Inhoud</h2><p>Eigen artikeltekst blijft behouden.</p>', 'slug' => 'behouden', 'language' => 'nl', 'categorie' => $category['id'],
            'featuredImageUrl' => 'https://images.example.com/photo.png', 'featuredImageAlt' => 'Artikelbeeld',
            'jsonLd' => json_encode(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => 'Behouden artikel'])];
        $source = $this->postJson('/api/socranext/v1/blog', $payload)->assertOk()->json();
        $translation = $this->postJson('/api/socranext/v1/blog', array_replace($payload, ['blogId' => 'offboard-en', 'titel' => 'Preserved article', 'language' => 'en', 'sourcePostId' => $source['id'], 'categorie' => $englishCategory['id']]))->assertOk()->json();
        return [$source, $translation];
    }

    private function faq(int $id, string $prefix = 'cpt/socranext_post/', bool $enabled = true): void
    {
        $this->postJson('/api/socranext/v1/'.$prefix.'store-aio-information', ['page_id' => $id, 'questions' => [['question' => 'Alleen de gekoppelde FAQ?', 'answer' => '<p>Dit antwoord wordt verwijderd.</p>']]])->assertOk();
        if ($enabled) $this->postJson('/api/socranext/v1/'.$prefix.'toggle', ['page_id' => $id, 'enabled' => true])->assertOk();
    }

    public function test_offboarding_requires_token_and_advertises_its_explicit_contract(): void
    {
        $this->postJson('/api/socranext/v1/offboard-faqs')->assertUnauthorized();
        $this->authenticate();
        $this->getJson('/api/socranext/v1/status')->assertOk()->assertJsonPath('capabilities.faq_only_offboarding', true)->assertJsonPath('last_offboarding', null);
        $before = file_get_contents(config('socranext.state_path'));
        $this->withHeader('x-socranext-token', 'wrong');
        $this->postJson('/api/socranext/v1/offboard-faqs')->assertUnauthorized();
        $this->assertSame($before, file_get_contents(config('socranext.state_path')));
    }

    public function test_faq_html_and_schema_disappear_but_native_articles_translations_assets_and_configuration_survive(): void
    {
        $this->authenticate();
        [$source, $translation] = $this->articles();
        Collection::make('pages')->routes('/{slug}')->save();
        $page = Entry::make()->collection('pages')->locale('default')->slug('customer-page')->published(true)->data(['title' => 'Customer page', 'content' => '<p>Customer content</p>']);
        $page->save();
        $pageId = app(ContentRepository::class)->identity($page);
        $store = app(StateStore::class);
        $store->put('frontend_ready', true);
        $store->put('styles', ['faq' => ['title_text' => 'Vragen'], 'blog' => ['layout' => 'v2', 'custom_css' => '.sn-article{color:#6d28d9}'], 'articles' => ['button_text' => 'Lees meer']]);
        $store->put('llms', ['llmsTxt' => '# Website', 'llmsFullTxt' => 'Native article index']);
        $store->put('metadata', [$pageId => ['title_tag' => 'Customer search title', 'meta_description' => 'Customer description']]);
        $this->faq($source['id']);
        $this->faq($translation['id']);
        $this->faq($pageId, '');
        $disabledPage = Entry::make()->collection('pages')->locale('default')->slug('disabled-page')->published(true)->data(['title' => 'Disabled FAQ page']);
        $disabledPage->save();
        $this->faq(app(ContentRepository::class)->identity($disabledPage), '', false);
        mkdir($this->temporaryDirectory.'/views');
        file_put_contents($this->temporaryDirectory.'/views/layout.antlers.html', '<html><head>{{ socranext:metadata }}</head><body>{{ template_content }}</body></html>');
        file_put_contents($this->temporaryDirectory.'/views/page.antlers.html', '{{ content }}{{ socranext:faq }}');
        view()->addNamespace('offboard', $this->temporaryDirectory.'/views');
        $render = fn ($entry) => View::make('socranext::public.entry')->cascadeContent($entry)->layout('offboard::layout')->render();
        $renderPage = fn () => View::make('offboard::page')->cascadeContent($page)->layout('offboard::layout')->render();
        $this->assertStringContainsString('FAQPage', $renderPage());
        $urls = [];
        foreach ([$source, $translation] as $article) {
            $entry = Entry::find($article['native_id']);
            Site::setCurrent($entry->locale());
            $html = $render($entry);
            $this->assertStringContainsString('FAQPage', $html);
            $this->assertStringContainsString('Alleen de gekoppelde FAQ?', $html);
            $this->assertStringContainsString('"@type":"Article"', $html);
            $this->assertStringContainsString('color:#6d28d9', $html);
            $urls[$article['native_id']] = $entry->absoluteUrl();
            $this->assertTrue(Asset::find($entry->get('featured_image'))->exists());
        }
        $archive = app(Renderer::class)->articles();
        $files = $this->files();
        $this->assertNotEmpty($files);
        $config = config('socranext');
        $preservedState = $this->preservedState();
        config(['statamic.static_caching.strategy' => 'full', 'statamic.static_caching.strategies.full.path' => $this->temporaryDirectory.'/static']);
        $cache = StaticCache::getFacadeRoot()->driver();
        $request = Request::create($source['url']);
        $cache->cachePage($request, $html);
        $this->assertTrue($cache->hasCachedPage($request));

        $this->postJson('/api/socranext/v1/offboard-faqs')->assertOk()->assertExactJson(['success' => true, 'articles_preserved' => true, 'deleted_faqs' => 4]);
        $this->assertFalse($cache->hasCachedPage($request));
        $this->assertArrayNotHasKey('faqs', $this->state());
        $this->getJson('/api/socranext/v1/status')->assertOk()->assertJsonPath('last_offboarding', self::FAQ_ONLY);
        foreach ([$source, $translation] as $article) {
            $entry = Entry::find($article['native_id']);
            Site::setCurrent($entry->locale());
            $html = $render($entry);
            $this->assertStringNotContainsString('FAQPage', $html);
            $this->assertStringNotContainsString('Alleen de gekoppelde FAQ?', $html);
            $this->assertStringContainsString('Eigen artikeltekst blijft behouden.', $html);
            $this->assertStringContainsString('"@type":"Article"', $html);
            $this->assertStringContainsString('color:#6d28d9', $html);
            $this->assertSame($urls[$article['native_id']], $entry->absoluteUrl());
            $this->assertSame($article['id'], app(ContentRepository::class)->identity($entry));
            $this->assertTrue(Asset::find($entry->get('featured_image'))->exists());
        }
        $this->assertSame($source['native_id'], Entry::find($translation['native_id'])->origin()->id());
        $this->assertSame($archive, app(Renderer::class)->articles());
        $this->assertStringNotContainsString('FAQPage', $renderPage());
        $this->assertStringNotContainsString('Alleen de gekoppelde FAQ?', $renderPage());
        $this->assertStringContainsString('Customer content', $renderPage());
        $this->assertStringContainsString('Customer search title', $renderPage());
        $this->assertSame($files, $this->files());
        $this->assertSame($config, config('socranext'));
        $this->assertSame($preservedState, $this->preservedState());
        $this->get('/llms.txt')->assertOk()->assertSee('# Website');
        $this->get('/llms-full.txt')->assertOk()->assertSee('Native article index');

        $this->postJson('/api/socranext/v1/offboard-faqs')->assertOk()->assertJsonPath('deleted_faqs', 0);
        $this->assertSame($files, $this->files());
        $this->assertSame($preservedState, $this->preservedState());
        $this->faq($source['id'], enabled: false);
        $this->assertSame('', app(Renderer::class)->faq($source['native_id']));
        $this->getJson('/api/socranext/v1/cpt/socranext_post/aio-information/'.$source['id'])->assertOk()->assertJsonPath('enabled', false)->assertJsonCount(1, 'questions');
        $this->postJson('/api/socranext/v1/cpt/socranext_post/toggle', ['page_id' => $source['id'], 'enabled' => true])->assertOk();
        $this->assertStringContainsString('FAQPage', app(Renderer::class)->faq($source['native_id']));
        $this->assertSame($files, $this->files());
    }

    public function test_cache_flush_observes_committed_deletion_and_failure_cannot_confirm_offboarding(): void
    {
        $this->authenticate();
        $store = app(StateStore::class);
        $store->put('faqs', [123 => ['enabled' => true, 'questions' => []]]);
        $store->put('last_offboarding', self::FAQ_ONLY);
        $preserved = $this->preservedState();
        config(['statamic.static_caching.strategy' => 'full']);
        $flushes = 0;
        StaticCache::shouldReceive('flush')->twice()->andReturnUsing(function () use (&$flushes) {
            $this->assertArrayNotHasKey('faqs', $this->state());
            $this->assertArrayNotHasKey('last_offboarding', $this->state());
            $probe = fopen(config('socranext.state_path').'.content.lock', 'c');
            try { $this->assertFalse(flock($probe, LOCK_EX | LOCK_NB), 'Mutation lock must cover cache invalidation and marker confirmation.'); }
            finally { fclose($probe); }
            if (++$flushes === 1) throw new \RuntimeException('Test cache failure');
        });
        $this->postJson('/api/socranext/v1/offboard-faqs')->assertStatus(500);
        $this->getJson('/api/socranext/v1/status')->assertOk()->assertJsonPath('last_offboarding', null);
        $this->assertSame($preserved, $this->preservedState());
        $this->postJson('/api/socranext/v1/offboard-faqs')->assertOk()->assertJsonPath('deleted_faqs', 0);
        $this->assertSame(2, $flushes);
        $this->getJson('/api/socranext/v1/status')->assertOk()->assertJsonPath('last_offboarding', self::FAQ_ONLY);
    }

    public function test_empty_offboarding_does_not_install_content_and_is_idempotent_without_readiness(): void
    {
        $this->authenticate();
        $before = $this->files();
        foreach ([1, 2] as $_) {
            $this->postJson('/api/socranext/v1/offboard-faqs')->assertOk()->assertExactJson(['success' => true, 'articles_preserved' => true, 'deleted_faqs' => 0]);
            $this->assertSame($before, $this->files());
            $this->assertNull(Collection::find('socranext_articles'));
            $this->getJson('/api/socranext/v1/status')->assertOk()->assertJsonPath('frontend_ready', false)->assertJsonPath('last_offboarding', self::FAQ_ONLY);
        }
    }

    public function test_lock_failure_leaves_all_state_unchanged(): void
    {
        $this->authenticate();
        app(StateStore::class)->put('faqs', [123 => ['enabled' => true, 'questions' => []]]);
        $before = file_get_contents(config('socranext.state_path'));
        $this->app->instance(MutationLock::class, new class extends MutationLock {
            public function run(callable $callback): mixed { throw new \RuntimeException('Test lock failure'); }
        });
        $this->postJson('/api/socranext/v1/offboard-faqs')->assertStatus(500);
        $this->assertSame($before, file_get_contents(config('socranext.state_path')));
    }

    public function test_explicit_full_purge_replaces_marker_before_partial_deletion_failure(): void
    {
        $this->authenticate();
        [$source, $translation] = $this->articles();
        $this->postJson('/api/socranext/v1/offboard-faqs')->assertOk();
        $rejectSource = true;
        Event::listen(EntryDeleting::class, function ($event) use ($source, &$rejectSource) {
            $this->assertSame(self::FULL_PURGE, app(StateStore::class)->get('last_offboarding'));
            return $rejectSource && $event->entry->id() === $source['native_id'] ? false : null;
        });
        $this->postJson('/api/socranext/v1/purge')->assertStatus(422);
        $this->assertNull(Entry::find($translation['native_id']));
        $this->assertNotNull(Entry::find($source['native_id']));
        $this->getJson('/api/socranext/v1/status')->assertOk()->assertJsonPath('last_offboarding', self::FULL_PURGE);
        $rejectSource = false;
        // Native Blink is request-scoped; Testbench keeps the same app across these HTTP calls.
        \Statamic\Facades\Blink::flush();
        $this->postJson('/api/socranext/v1/purge')->assertOk();
        $this->assertNull(Entry::find($source['native_id']));
        $this->getJson('/api/socranext/v1/status')->assertOk()->assertJsonPath('last_offboarding', self::FULL_PURGE);
    }
}
