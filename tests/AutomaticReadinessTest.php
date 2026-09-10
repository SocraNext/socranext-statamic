<?php

namespace SocraNext\Statamic\Tests;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use SocraNext\Statamic\Support\{Readiness, Setup, StateStore};
use Statamic\Facades\{Blueprint, Collection, Entry, Site, Taxonomy};
use Statamic\Http\Middleware\AddViewPaths;

class AutomaticReadinessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Blueprint::setDirectory($this->temporaryDirectory.'/blueprints');
    }

    private function setupSite(bool $multisite = false): void
    {
        if ($multisite) {
            Site::setSites(['default' => ['name' => 'English', 'locale' => 'en_US', 'url' => 'https://example.com/'],
                'nl' => ['name' => 'Dutch', 'locale' => 'nl_NL', 'url' => 'https://example.com/nl/']]);
            config(['statamic.system.multisite' => true, 'socranext.content.sites' => ['default', 'nl']]);
        }
        app(Setup::class)->prepare();
        $this->assertTrue(app(Readiness::class)->ready());
    }

    public static function invalidResources(): array
    {
        return array_map(fn ($value) => [$value], ['article_sites', 'taxonomy_sites', 'archive_sites', 'article_route', 'archive_ownership', 'archive_protection', 'article_blueprint']);
    }

    #[DataProvider('invalidResources')]
    public function test_changed_native_resources_are_not_reported_ready(string $change): void
    {
        $this->setupSite(true);
        $archiveId = app(StateStore::class)->get('archive_entries')['nl'];
        $archive = Entry::find($archiveId);
        match ($change) {
            'article_sites' => Collection::find('socranext_articles')->sites(['default'])->save(),
            'taxonomy_sites' => Taxonomy::find('socranext_categories')->sites(['default'])->save(),
            'archive_sites' => Collection::find('socranext_archives')->sites(['default'])->save(),
            'article_route' => Collection::find('socranext_articles')->routes(['default' => '/artikelen-sn/{socranext_path}', 'nl' => null])->save(),
            'archive_ownership' => $archive->set('socranext_owned', false)->save(),
            'archive_protection' => $archive->set('protect', 'password')->save(),
            'article_blueprint' => Blueprint::find('collections/socranext_articles/article')->delete(),
        };
        $state = file_get_contents(config('socranext.state_path'));
        $this->assertFalse(app(Readiness::class)->checks()['article_collection']);
        $this->assertFalse(app(Readiness::class)->ready());
        $this->assertSame($state, file_get_contents(config('socranext.state_path')));
        $this->assertSame(['default', 'nl'], config('socranext.content.sites'));
    }

    public static function viewLocations(): array
    {
        return ['ordinary native view paths' => [false], 'namespaced theme hints' => [true]];
    }

    #[DataProvider('viewLocations')]
    public function test_each_site_uses_its_own_views_without_changing_request_paths_hints_or_cache(bool $namespaced): void
    {
        $this->setupSite(true);
        $directory = $this->temporaryDirectory.'/theme';
        $prefix = $namespaced ? 'readiness_theme::' : '';
        foreach (['default', 'nl'] as $site) mkdir($directory.'/'.$site, 0755, true);
        foreach (['article', 'archive', 'theme'] as $view) file_put_contents($directory.'/default/'.$view.'.antlers.html', '<main>Default site</main>');
        if ($namespaced) view()->addNamespace('readiness_theme', $directory);
        else view()->addLocation($directory);
        Collection::find('socranext_articles')->template($prefix.'article')->layout($prefix.'theme')->save();
        Collection::find('socranext_archives')->template($prefix.'archive')->layout($prefix.'theme')->save();
        Site::setCurrent('default');
        $beforeSite = Site::current();
        $finder = view()->getFinder();
        $outer = [$finder->getPaths(), $finder->getHints()];
        (new AddViewPaths)->handle(Request::create('https://example.com/'), function () use ($directory, $prefix, $finder, $beforeSite) {
            // Prime the real request cache with the default site's existing template.
            $finder->find($prefix.'article');
            $before = [$finder->getPaths(), $finder->getHints(), $finder->getViews()];
            $this->assertFalse(app(Readiness::class)->checks()['templates']);
            foreach (['article', 'archive', 'theme'] as $view) file_put_contents($directory.'/nl/'.$view.'.antlers.html', '<main>Dutch site</main>');
            $this->assertTrue(app(Readiness::class)->checks()['templates']);
            // Removing only the non-current site's layout must still invalidate readiness.
            unlink($directory.'/nl/theme.antlers.html');
            $this->assertFalse(app(Readiness::class)->checks()['templates']);
            $this->assertSame($finder, view()->getFinder());
            $this->assertSame($before, [$finder->getPaths(), $finder->getHints(), $finder->getViews()]);
            $this->assertSame($beforeSite, Site::current());
            return response('Native request unaffected');
        });
        $this->assertSame($outer, [$finder->getPaths(), $finder->getHints()]);
    }

    public function test_native_blade_template_does_not_require_the_unused_collection_layout(): void
    {
        $this->setupSite();
        $directory = $this->temporaryDirectory.'/standalone-theme';
        mkdir($directory);
        file_put_contents($directory.'/article.blade.php', '<html><body>Complete Blade template</body></html>');
        view()->addNamespace('standalone', $directory);
        Collection::find('socranext_articles')->template('standalone::article')->layout('unused-layout')->save();
        $this->assertTrue(app(Readiness::class)->checks()['templates']);
    }

    public function test_broken_image_root_and_disabled_native_routes_are_reported_without_creating_files(): void
    {
        $this->setupSite();
        $missing = $this->temporaryDirectory.'/missing-images';
        config(['filesystems.disks.socranext_public.root' => $missing]);
        $this->assertFalse(app(Readiness::class)->checks()['image_storage']);
        $this->assertDirectoryDoesNotExist($missing);
        config(['statamic.routes.enabled' => false]);
        $this->assertFalse(app(Readiness::class)->checks()['native_routes']);
        $this->assertFalse(app(Readiness::class)->ready());
        $this->assertDirectoryDoesNotExist($missing);
    }

    public function test_manual_readiness_retains_the_explicit_legacy_confirmation_contract(): void
    {
        config(['socranext.frontend.mode' => 'manual', 'statamic.routes.enabled' => false]);
        $this->assertFalse(app(Readiness::class)->ready());
        app(StateStore::class)->put('frontend_ready', true);
        $this->assertTrue(app(Readiness::class)->ready());
        $this->assertNull(Collection::find('socranext_articles'));
        config(['socranext.content.sites' => ['unknown']]);
        $this->assertFalse(app(Readiness::class)->ready());
    }
}
