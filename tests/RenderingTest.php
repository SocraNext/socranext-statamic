<?php

namespace SocraNext\Statamic\Tests;

use SocraNext\Statamic\Content\ContentRepository;
use SocraNext\Statamic\Rendering\{CodeSignature, PreviewSession, Renderer, SafeMarkup};
use SocraNext\Statamic\Support\{Connection, StateStore};
use Statamic\Facades\{Collection, Entry};

class RenderingTest extends TestCase
{
    private function authenticate(): void
    {
        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('a', 64));
        $this->withHeader('x-socranext-token', str_repeat('a', 64));
    }

    private function page(string $collection = 'pages', bool $published = true)
    {
        Collection::make($collection)->routes('/{slug}')->save();
        $entry = Entry::make()->collection($collection)->locale('default')->slug('test-'.bin2hex(random_bytes(3)))->published($published)
            ->data(['title' => 'Native article', 'content' => '<h2>Heading</h2><p>Real body</p>', 'socranext_owned' => true]);
        $entry->save();
        return $entry;
    }

    public function test_faq_is_persistent_disabled_until_ready_and_uses_safe_public_dom(): void
    {
        $this->authenticate();
        $page = $this->page();
        $id = app(ContentRepository::class)->identity($page);
        $payload = ['page_id' => $id, 'questions' => [['question' => '<script>attack</script>Vraag?', 'answer' => '<p>Antwoord <strong>vet</strong><img src="x" onerror="alert(1)"><script>alert(1)</script><a href="javascript:alert(1)">link</a></p>']]];
        $this->postJson('/api/socranext/v1/store-aio-information', $payload)->assertOk();
        $this->assertSame('', app(Renderer::class)->faq($page->id()));
        $this->postJson('/api/socranext/v1/toggle', ['page_id' => $id, 'enabled' => true])->assertStatus(409);
        app(StateStore::class)->put('frontend_ready', true);
        $this->postJson('/api/socranext/v1/toggle', ['page_id' => $id, 'enabled' => true])->assertOk();
        $html = app(Renderer::class)->faq($page->id());
        $this->assertStringContainsString('socranext-qalist', $html);
        $this->assertStringContainsString('<strong>vet</strong>', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $schema);
        $decoded = json_decode($schema[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('FAQPage', $decoded['@type']);
        $this->getJson('/api/socranext/v1/aio-information/'.$id)->assertOk()->assertJsonPath('enabled', true);
        $this->postJson('/api/socranext/v1/toggle', ['page_id' => $id, 'enabled' => false])->assertOk();
        $this->assertSame('', app(Renderer::class)->faq($page->id()));
    }

    public function test_unsigned_or_wrong_site_slot_code_cannot_be_stored_or_executed(): void
    {
        $this->authenticate();
        $keys = sodium_crypto_sign_keypair();
        config(['socranext.code_signing_public_key' => base64_encode(sodium_crypto_sign_publickey($keys))]);
        $code = 'window.socraNextTest = 1;';
        $signature = base64_encode(sodium_crypto_sign_detached("socranext.code.v1\nexample.com\nfaq\n".$code, sodium_crypto_sign_secretkey($keys)));
        $this->postJson('/api/socranext/v1/store-faq-custom', ['custom_js' => $code])->assertStatus(422);
        $this->postJson('/api/socranext/v1/store-faq-custom', ['custom_js' => $code, 'custom_js_sig' => $signature])->assertOk();
        $this->postJson('/api/socranext/v1/store-blog-custom', ['custom_js' => $code, 'custom_js_sig' => $signature])->assertStatus(422);
        $this->assertStringContainsString($code, app(Renderer::class)->scripts('faq', app(Renderer::class)->style('faq')));
        config(['socranext.site_url' => 'https://other.example']);
        $this->assertFalse(app(CodeSignature::class)->valid('faq', $code, $signature));
        $this->assertSame('', app(Renderer::class)->scripts('faq', app(Renderer::class)->style('faq')));
    }

    public function test_styles_preserve_translations_and_reject_html_breakout(): void
    {
        $this->authenticate();
        $this->postJson('/api/socranext/v1/store-faq-custom', ['title_text_i18n' => ['en' => 'Questions'], 'custom_css' => '.socranext-root{--sn-faq-title-color:red}'])->assertOk();
        $this->postJson('/api/socranext/v1/store-faq-custom', ['title_text' => 'Vragen'])->assertOk();
        $this->assertSame('Questions', app(Renderer::class)->style('faq', 'en')['title_text']);
        $this->postJson('/api/socranext/v1/store-faq-custom', ['custom_css' => '</style><script>alert(1)</script>'])->assertStatus(422);
        $this->postJson('/api/socranext/v1/store-faq-custom', ['custom_html' => '<p>hello</p><svg onload="bad()"></svg>'])->assertOk();
        $this->assertSame('<p>hello</p>', app(Renderer::class)->style('faq')['custom_html']);
        $this->postJson('/api/socranext/v1/store-blog-custom', ['layout_settings' => ['ctaTitle' => ['bad']]])->assertStatus(422);
    }

    public function test_preview_requires_unexpired_origin_bound_token_and_no_store(): void
    {
        $this->authenticate();
        config(['socranext.preview_origins' => ['https://platform.socranext.ai']]);
        $this->postJson('/api/socranext/v1/preview-token', ['origin' => 'https://evil.example'])->assertStatus(422);
        $this->postJson('/api/socranext/v1/preview-token', ['origin' => 'https://platform.socranext.ai/path'])->assertStatus(422);
        $this->get('/socranext/preview/faq?token=forged')->assertForbidden();
        $response = $this->postJson('/api/socranext/v1/preview-token', ['origin' => 'https://platform.socranext.ai'])->assertOk();
        $token = $response->json('token');
        foreach (['faq','article','articles'] as $kind) {
            $preview = $this->get('/socranext/preview/'.$kind.'?socranext_token='.rawurlencode($token));
            $preview->assertOk()->assertSee('socranext:ready')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertStringContainsString('no-store', $preview->headers->get('Cache-Control'));
            $this->assertStringContainsString('event.origin!==origin', $preview->getContent());
        }
        $expired = \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['origin' => 'https://platform.socranext.ai', 'expires' => time()-1]));
        $this->get('/socranext/preview/faq?token='.rawurlencode($expired))->assertForbidden();
    }

    public function test_public_articles_exclude_drafts_and_share_v2_css_markup(): void
    {
        $public = $this->page('socranext_articles');
        $draft = $this->page('socranext_articles', false);
        $draft->set('title', 'Secret unpublished')->save();
        app(StateStore::class)->put('styles', ['blog' => ['layout' => 'v2', 'layout_settings' => ['showToc' => true, 'showCta' => true, 'ctaTitle' => 'Call us', 'ctaButtonUrl' => 'javascript:bad()', 'ctaButtonText' => 'Bad']]]);
        $html = app(Renderer::class)->article($public);
        $this->assertStringContainsString('sn-layout-v2', $html);
        $this->assertStringContainsString('sn-heading-1', $html);
        $this->assertStringContainsString('Call us', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertSame('', app(Renderer::class)->article($draft));
        $this->assertStringNotContainsString('Secret unpublished', app(Renderer::class)->articles());
    }

    public function test_llms_text_is_published_and_authentication_is_required_for_writes(): void
    {
        $this->postJson('/api/socranext/v1/llmstxt', ['llmsTxt' => '# Site'])->assertUnauthorized();
        $this->authenticate();
        $this->postJson('/api/socranext/v1/llmstxt', ['llmsTxt' => '# Site', 'llmsFullTxt' => 'Full text'])->assertOk();
        $this->get('/llms.txt')->assertOk()->assertSee('# Site')->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->get('/llms-full.txt')->assertOk()->assertSee('Full text');
    }

    public function test_native_antlers_wrapper_uses_site_layout_and_metadata_escapes_script_endings(): void
    {
        $entry = $this->page('socranext_articles');
        $entry->set('socranext_title_tag', 'Search title')->set('socranext_meta_description', '" onload="bad')
            ->set('socranext_json_ld', ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => '</script><script>bad()</script>'])->save();
        $dir = $this->temporaryDirectory.'/views';
        mkdir($dir);
        file_put_contents($dir.'/layout.antlers.html', '<html><head>{{ socranext:metadata }}</head><body><header>Website theme</header>{{ template_content }}</body></html>');
        view()->addNamespace('socratest', $dir);
        $html = \Statamic\View\View::make('socranext::public.entry')->cascadeContent($entry)->layout('socratest::layout')->render();
        $this->assertStringContainsString('<header>Website theme</header>', $html);
        $this->assertStringContainsString('Real body', $html);
        $this->assertStringContainsString('<title>Search title</title>', $html);
        $this->assertStringNotContainsString('</script><script>bad()', $html);
        $this->authenticate();
        config(['socranext.content.article_layout' => 'socratest::layout', 'socranext.preview_origins' => ['https://platform.socranext.ai']]);
        $token = app(PreviewSession::class)->issue('https://platform.socranext.ai')['token'];
        $this->get('/socranext/preview/article?token='.rawurlencode($token))->assertOk()->assertSee('Website theme')->assertSee('socranext:ready');
    }

    public function test_numeric_tag_cannot_reveal_a_draft_and_cpt_routes_resolve_identity(): void
    {
        $this->authenticate();
        $entry = $this->page('socranext_articles');
        $id = app(ContentRepository::class)->identity($entry);
        $this->postJson('/api/socranext/v1/cpt/socranext_post/store-aio-information', ['page_id' => $id, 'questions' => [['question' => 'CPT question', 'answer' => 'Answer']]])->assertOk();
        app(StateStore::class)->put('frontend_ready', true);
        $this->postJson('/api/socranext/v1/cpt/socranext_post/toggle', ['page_id' => $id, 'enabled' => true])->assertOk();
        $this->getJson('/api/socranext/v1/cpt/socranext_post/aio-information/'.$id)->assertOk()->assertJsonPath('enabled', true);
        $entry->published(false)->save();
        $this->assertSame('', app(Renderer::class)->faq($id));
    }

    public function test_style_publication_invalidates_real_static_cache_files(): void
    {
        $this->authenticate();
        config(['statamic.static_caching.strategy' => 'full', 'statamic.static_caching.strategies.full.path' => $this->temporaryDirectory.'/static']);
        $cache = \Statamic\Facades\StaticCache::getFacadeRoot()->driver();
        $request = \Illuminate\Http\Request::create('https://example.com/known');
        $cache->cachePage($request, '<p>Old FAQ output</p>');
        $this->assertTrue($cache->hasCachedPage($request));
        $this->postJson('/api/socranext/v1/store-faq-custom', ['title_text' => 'Updated'])->assertOk();
        $this->assertFalse($cache->hasCachedPage($request));
    }

    public function test_metadata_overrides_render_for_existing_native_page_and_term(): void
    {
        $page = $this->page();
        $page->set('socranext_owned', false)->save();
        $pageId = app(ContentRepository::class)->identity($page);
        $this->assertSame('', app(Renderer::class)->metadata($page));
        \Statamic\Facades\Taxonomy::make('topics')->sites(['default'])->save();
        $term = \Statamic\Facades\Term::make()->taxonomy('topics')->slug('testing')->data(['title' => 'Testing']);
        $term->save();
        $localized = $term->in('default');
        $termId = app(ContentRepository::class)->identity($localized);
        app(StateStore::class)->put('metadata', [
            $pageId => ['title_tag' => 'Page search title', 'meta_description' => 'Page description'],
            $termId => ['title_tag' => 'Term search title', 'meta_description' => 'Term description'],
        ]);
        $pageHtml = app(Renderer::class)->metadata($page);
        $termHtml = app(Renderer::class)->metadata($localized);
        $this->assertStringContainsString('<title>Page search title</title>', $pageHtml);
        $this->assertStringContainsString('<title>Term search title</title>', $termHtml);
        $this->assertStringNotContainsString('Page search title', $termHtml);
        $this->assertSame('Native article', $page->get('title'));
    }

    public function test_term_metadata_is_separate_per_native_site_and_preview_is_bound_to_installation(): void
    {
        $this->authenticate();
        \Statamic\Facades\Site::setSites(['default' => ['name' => 'Dutch', 'locale' => 'nl_NL', 'url' => '/'], 'english' => ['name' => 'English', 'locale' => 'en_GB', 'url' => '/en/']]);
        \Statamic\Facades\Taxonomy::make('topics')->sites(['default', 'english'])->save();
        $term = \Statamic\Facades\Term::make()->taxonomy('topics')->slug('topic')->data(['title' => 'Topic']);
        $term->save();
        $dutch = $term->in('default');
        $english = $term->in('english');
        $dutchId = app(ContentRepository::class)->identity($dutch);
        $englishId = app(ContentRepository::class)->identity($english);
        $this->assertNotSame($dutchId, $englishId);
        app(StateStore::class)->put('metadata', [$dutchId => ['title_tag' => 'Nederlands'], $englishId => ['title_tag' => 'English']]);
        $this->assertStringContainsString('<title>Nederlands</title>', app(Renderer::class)->metadata($dutch));
        $this->assertStringContainsString('<title>English</title>', app(Renderer::class)->metadata($english));
        config(['socranext.preview_origins' => ['https://platform.socranext.ai']]);
        $session = app(PreviewSession::class)->issue('https://platform.socranext.ai');
        config(['socranext.site_url' => 'https://different.example']);
        $this->get('/socranext/preview/faq?token='.rawurlencode($session['token']))->assertForbidden();
    }

    public function test_styler_translations_normalize_locales_and_keep_global_layout_options(): void
    {
        $this->authenticate();
        $this->postJson('/api/socranext/v1/store-blog-custom', ['layout' => 'v2', 'layout_settings' => ['showToc' => false, 'ctaButtonUrl' => 'https://example.com/contact', 'ctaTitle' => 'Basis'], 'layout_settings_i18n' => ['en' => ['ctaTitle' => 'Translated', 'tocTitle' => 'Contents']]])->assertOk();
        $style = app(Renderer::class)->style('blog', 'EN_gb');
        $this->assertSame('Translated', $style['layout_settings']['ctaTitle']);
        $this->assertSame(false, $style['layout_settings']['showToc']);
        $this->assertSame('https://example.com/contact', $style['layout_settings']['ctaButtonUrl']);
        $this->assertSame('Basis', app(Renderer::class)->style('blog', 'de_DE')['layout_settings']['ctaTitle']);
        $this->postJson('/api/socranext/v1/store-blog-custom', ['layout_settings_i18n' => []])->assertOk();
        $this->assertSame('Basis', app(Renderer::class)->style('blog', 'en')['layout_settings']['ctaTitle']);
    }
}
