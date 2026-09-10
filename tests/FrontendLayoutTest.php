<?php

namespace SocraNext\Statamic\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use SocraNext\Statamic\Content\ArticlePublisher;
use SocraNext\Statamic\Rendering\{FrontendLayout, PreviewSession};
use SocraNext\Statamic\Support\{Connection, Readiness, Setup, StateStore};
use Statamic\Facades\{Blueprint, Collection, Site};

class FrontendLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Blueprint::setDirectory($this->temporaryDirectory.'/blueprints');
        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('l', 64));
        app(StateStore::class)->put('styles', ['blog' => ['layout' => 'v2']]);
    }

    private function preview(): string
    {
        $token = app(PreviewSession::class)->issue('https://platform.socranext.ai')['token'];
        return $this->get('/socranext/preview/article?token='.rawurlencode($token))->assertOk()
            ->assertSee('socranext:ready')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->getContent();
    }

    private function publish(string $site = 'default'): void
    {
        app(ArticlePublisher::class)->publish(['blogId' => 'layout-'.$site, 'titel' => 'SocraNext article '.$site,
            'tekst' => '<p>Own styled body '.$site.'</p>', 'slug' => 'layout-'.$site, 'site' => $site]);
    }

    public static function themeLocations(): array
    {
        return ['native site view paths' => [false], 'namespaced site view hints' => [true]];
    }

    #[DataProvider('themeLocations')]
    public function test_site_specific_antlers_system_layout_keeps_native_shell_and_socranext_preview_content(bool $namespaced): void
    {
        Site::setSites(['default' => ['name' => 'English', 'locale' => 'en_US', 'url' => 'https://example.com/'],
            'nl' => ['name' => 'Dutch', 'locale' => 'nl_NL', 'url' => 'https://example.com/nl/']]);
        config(['statamic.system.multisite' => true, 'socranext.content.sites' => ['default', 'nl']]);
        $directory = $this->temporaryDirectory.'/site-theme';
        $name = $namespaced ? 'layout_theme::theme' : 'theme';
        foreach (['default', 'nl'] as $site) {
            mkdir($directory.'/'.$site, 0755, true);
            file_put_contents($directory.'/'.$site.'/theme.antlers.html', '<html><head><title>{{ title }}</title></head><body><header>Native '.$site.'</header><main>{{ template_content }}</main><footer>Theme footer '.$site.'</footer></body></html>');
        }
        if ($namespaced) view()->addNamespace('layout_theme', $directory);
        else view()->addLocation($directory);
        config(['statamic.system.layout' => $name]);
        $this->assertSame($name, FrontendLayout::resolve());
        app(Setup::class)->prepare();
        $this->assertTrue(app(Readiness::class)->ready());
        $this->assertSame($name, Collection::find('socranext_articles')->layout());
        foreach (['default', 'nl'] as $site) $this->publish($site);
        foreach (['default', 'nl'] as $site) {
            Site::setCurrent($site);
            $finder = view()->getFinder();
            $before = [$finder->getPaths(), $finder->getHints(), $finder->getViews()];
            $html = $this->preview();
            $this->assertStringContainsString('<header>Native '.$site.'</header>', $html);
            $this->assertStringContainsString('Theme footer '.$site, $html);
            $this->assertStringContainsString('Own styled body '.$site, $html);
            $this->assertStringContainsString('sn-layout-v2', $html);
            $this->assertSame($finder, view()->getFinder());
            // Rendering the article itself can populate ordinary view cache entries;
            // selecting its per-site outer layout must not alter paths or hints.
            $this->assertSame(array_slice($before, 0, 2), [$finder->getPaths(), $finder->getHints()]);
        }
    }

    public function test_default_blade_yield_shell_falls_back_without_losing_article_content_or_socranext_styling(): void
    {
        $directory = $this->temporaryDirectory.'/blade-theme';
        mkdir($directory);
        file_put_contents($directory.'/theme.blade.php', '<html><head></head><body><header>Blade shell</header>@yield("body")</body></html>');
        view()->addNamespace('blade_theme', $directory);
        config(['statamic.system.layout' => 'blade_theme::theme']);
        $this->assertSame('socranext::public.layout', FrontendLayout::resolve());
        app(Setup::class)->prepare();
        $this->publish();
        $html = $this->preview();
        $this->assertStringContainsString('Own styled body default', $html);
        $this->assertStringContainsString('sn-layout-v2', $html);
        $this->assertStringNotContainsString('Blade shell', $html);
    }

    public function test_antlers_layout_forwarding_content_through_a_partial_remains_supported(): void
    {
        $directory = $this->temporaryDirectory.'/partial-theme';
        mkdir($directory);
        file_put_contents($directory.'/theme.antlers.html', '{{ partial src="layout_parts::chrome" }}');
        file_put_contents($directory.'/chrome.antlers.html', '<html><head></head><body><header>Forwarded shell</header>{{ template_content }}</body></html>');
        view()->addNamespace('layout_parts', $directory);
        config(['statamic.system.layout' => 'layout_parts::theme']);
        app(Setup::class)->prepare();
        $this->publish();
        $html = $this->preview();
        $this->assertStringContainsString('Forwarded shell', $html);
        $this->assertStringContainsString('Own styled body default', $html);
    }

    public function test_explicit_blade_bridge_and_existing_collection_layout_are_preserved(): void
    {
        $directory = $this->temporaryDirectory.'/explicit-theme';
        mkdir($directory);
        file_put_contents($directory.'/theme.blade.php', '<html><head></head><body><header>Explicit bridge</header>{!! $template_content !!}</body></html>');
        view()->addNamespace('explicit_theme', $directory);
        config(['socranext.content.article_layout' => 'explicit_theme::theme']);
        $this->assertSame('explicit_theme::theme', FrontendLayout::resolve());
        app(Setup::class)->prepare();
        $this->publish();
        $html = $this->preview();
        $this->assertStringContainsString('Explicit bridge', $html);
        $this->assertStringContainsString('Own styled body default', $html);
        $before = file_get_contents(Collection::find('socranext_articles')->path());
        config(['socranext.content.article_layout' => 'layout']);
        app(Setup::class)->prepare();
        $this->assertSame($before, file_get_contents(Collection::find('socranext_articles')->path()));
    }
}
