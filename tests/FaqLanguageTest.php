<?php

namespace SocraNext\Statamic\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use SocraNext\Statamic\Content\ContentRepository;
use SocraNext\Statamic\Support\{Connection, StateStore};
use Statamic\Facades\{Collection, Entry, Site, Taxonomy, Term};

class FaqLanguageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('f', 64));
        $this->withHeader('x-socranext-token', str_repeat('f', 64));
        Site::setSites(['default' => ['name' => 'Dutch', 'locale' => 'nl_NL', 'url' => '/'], 'english' => ['name' => 'English', 'locale' => 'en_GB', 'url' => '/en/']]);
        config(['socranext.content.sites' => ['default', 'english'], 'statamic.system.multisite' => true,
            'socranext.frontend.mode' => 'manual', 'socranext.content.discovery' => 'configured',
            'socranext.content.collections' => ['pages' => ['pages'], 'posts' => ['news'], 'products' => ['products'], 'custom' => ['guides']],
            'socranext.content.taxonomies' => ['topics']]);
        app(StateStore::class)->put('frontend_ready', true);
    }

    public static function types(): array
    {
        return [['pages', ''], ['news', 'post-'], ['products', 'product-'], ['guides', 'cpt/guides/'], ['topics', 'category-']];
    }

    private function ids(string $collection): array
    {
        $resources = [];
        if ($collection === 'topics') {
            Taxonomy::make('topics')->sites(['default', 'english'])->save();
            $term = Term::make()->taxonomy('topics')->slug('topic')->data(['title' => 'Topic']);
            $term->save();
            foreach (['default', 'english'] as $site) {
                $resource = $term->in($site)->set('title', $site);
                $resource->save();
                $resources[] = $resource;
            }
        } else {
            Collection::make($collection)->sites(['default', 'english'])->routes('/'.$collection.'/{slug}')->save();
            foreach (['default', 'english'] as $site) {
                $resource = Entry::make()->id($collection.'-'.$site)->collection($collection)->locale($site)->slug('page')->published(true)->set('title', $site);
                $resource->save();
                $resources[] = $resource;
            }
        }
        return array_map(fn ($r) => app(ContentRepository::class)->identity($r), $resources);
    }

    #[DataProvider('types')]
    public function test_faq_operations_validate_native_locale_before_mutation(string $collection, string $prefix): void
    {
        [$nl, $en] = $this->ids($collection);
        $this->assertNotSame($nl, $en);
        $store = '/api/socranext/v1/'.(str_ends_with($prefix, '-') ? 'store-'.$prefix.'aio-information' : $prefix.'store-aio-information');
        $toggle = '/api/socranext/v1/'.$prefix.'toggle';
        $read = '/api/socranext/v1/'.$prefix.'aio-information/';
        foreach ([[$nl, 'nl', 'default'], [$en, 'en', 'english']] as [$id, $language, $site]) {
            $this->postJson($store, ['page_id' => $id, 'language' => $language, 'site_id' => $site, 'questions' => [['question' => $language.' question?', 'answer' => 'Answer']]])->assertOk();
            $this->postJson($toggle, ['page_id' => $id, 'language' => $language, 'enabled' => true])->assertOk();
            $this->getJson($read.$id.'?language='.$language.'&site_id='.$site)->assertOk()->assertJsonPath('language', $language)->assertJsonPath('questions.0.question', $language.' question?');
        }
        $before = app(StateStore::class)->get('faqs');
        foreach ([['language' => 'en'], ['language' => 'nl', 'site_id' => 'english'], ['language' => 'nl-NL'], ['language' => 'unknown']] as $wrong) {
            $this->postJson($store, $wrong + ['page_id' => $nl, 'questions' => []])->assertConflict();
            $this->postJson($toggle, $wrong + ['page_id' => $nl, 'enabled' => false])->assertConflict();
            $this->getJson($read.$nl.'?'.http_build_query($wrong))->assertConflict();
        }
        foreach ([['language' => ''], ['language' => 'all'], ['language' => null], ['site_id' => 'default'], ['language' => ['nl']]] as $invalid) {
            $this->postJson($store, $invalid + ['page_id' => $nl, 'questions' => []])->assertUnprocessable();
            $this->postJson($toggle, $invalid + ['page_id' => $nl, 'enabled' => false])->assertUnprocessable();
        }
        $this->assertSame($before, app(StateStore::class)->get('faqs'));
        $this->postJson($toggle, ['page_id' => $en, 'language' => 'en', 'enabled' => false])->assertOk();
        $this->getJson($read.$nl.'?language=nl')->assertOk()->assertJsonPath('enabled', true);
        $this->getJson($read.$en.'?language=en')->assertOk()->assertJsonPath('enabled', false);
        // Existing clients without a scope retain native-ID behavior.
        $this->postJson($toggle, ['page_id' => $en, 'enabled' => true])->assertOk();
        $this->getJson($read.$en)->assertOk()->assertJsonPath('enabled', true);
        $this->getJson('/api/socranext/v1/status')->assertOk()->assertJsonPath('capabilities.faq_language', true);
    }

    public function test_duplicate_base_language_requires_exported_regional_locale_and_exact_site(): void
    {
        Site::setSites(['default' => ['name' => 'UK', 'locale' => 'en_GB', 'url' => '/'], 'english' => ['name' => 'US', 'locale' => 'en_US', 'url' => '/us/']]);
        [$uk] = $this->ids('pages');
        $this->getJson('/api/socranext/v1/pages/'.$uk)->assertOk()->assertJsonPath('language', 'en-GB');
        $this->getJson('/api/socranext/v1/pages?lang=all')->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.language', 'en-GB')->assertJsonPath('1.language', 'en-US');
        $this->postJson('/api/socranext/v1/toggle', ['page_id' => $uk, 'language' => 'en-GB', 'site_id' => 'default', 'enabled' => false])->assertOk();
        foreach (['en', 'en-US'] as $wrong) $this->postJson('/api/socranext/v1/toggle', ['page_id' => $uk, 'language' => $wrong, 'enabled' => false])->assertConflict();
        $this->getJson('/api/socranext/v1/translations?id='.$uk.'&type=page&language=en-US')->assertConflict();
    }

    public static function catalogs(): array
    {
        return [['pages', 'pages'], ['news', 'posts'], ['products', 'products'],
            ['guides', 'cpt/guides/posts'], ['socranext_articles', 'cpt/socranext_post/posts'], ['topics', 'categories']];
    }

    #[DataProvider('catalogs')]
    public function test_all_language_discovery_paginates_actual_variants_without_changing_default_scope(string $collection, string $path): void
    {
        [$nl, $en] = $this->ids($collection);
        $endpoint = '/api/socranext/v1/'.$path;
        $this->getJson($endpoint)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $nl);
        $this->getJson($endpoint.'?lang=en')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $en);
        $this->getJson($endpoint.'?lang=all&site=english')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $en);
        $this->getJson($endpoint.'?lang=all&site=unknown')->assertUnprocessable();

        $all = [];
        foreach ([1, 2] as $page) {
            $result = $this->getJson($endpoint.'?lang=all&per_page=1&page='.$page)->assertOk()
                ->assertHeader('X-WP-Total', '2')->assertHeader('X-WP-TotalPages', '2')->assertJsonCount(1)->json();
            $all[] = $result[0];
        }
        $this->assertSame([$nl, $en], array_column($all, 'id'));
        $this->assertSame(['nl', 'en'], array_column($all, 'language'));
        $this->assertSame(['default', 'english'], array_column($all, 'site'));
        $this->getJson($endpoint.'?lang=all&per_page=1&page=3')->assertOk()->assertExactJson([])
            ->assertHeader('X-WP-Total', '2')->assertHeader('X-WP-TotalPages', '2');
        $this->getJson('/api/socranext/v1/search-url?lang=all&search='.rawurlencode($all[1]['url']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $en)->assertJsonPath('0.language', 'en');
    }

    public function test_all_language_search_excludes_disconnected_sites_drafts_and_missing_term_translations(): void
    {
        $this->ids('pages');
        $this->ids('socranext_articles');
        [$nlTerm] = $this->ids('topics');
        // The term exists only in Dutch after removing the explicit English data.
        Term::find('topics::topic')->in('english')->data([])->save();
        Site::setSites(['default' => ['name' => 'Dutch', 'locale' => 'nl_NL', 'url' => '/'],
            'english' => ['name' => 'English', 'locale' => 'en_GB', 'url' => '/en/'],
            'german' => ['name' => 'German', 'locale' => 'de_DE', 'url' => '/de/']]);
        Collection::find('pages')->sites(['default', 'english', 'german'])->save();
        Entry::make()->id('disconnected')->collection('pages')->locale('german')->slug('disconnected')->published(true)->set('title', 'Foreign')->save();
        Entry::make()->id('draft')->collection('pages')->locale('english')->slug('draft')->published(false)->set('title', 'Draft')->save();

        $this->getJson('/api/socranext/v1/categories?lang=all')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $nlTerm);
        $rows = [];
        foreach ([1, 2, 3] as $page) {
            $part = $this->getJson('/api/socranext/v1/search-url?lang=all&per_page=2&page='.$page)->assertOk()
                ->assertHeader('X-WP-Total', '5')->assertHeader('X-WP-TotalPages', '3')->json();
            array_push($rows, ...$part);
        }
        $this->assertCount(5, array_unique(array_column($rows, 'id')));
        $this->assertSame(['default', 'english'], array_values(array_unique(array_column($rows, 'site'))));
        $this->assertNotContains('disconnected', array_column($rows, 'native_id'));
        $this->assertNotContains('draft', array_column($rows, 'native_id'));
        $this->getJson('/api/socranext/v1/search-url?lang=all&search=english')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/socranext/v1/search-url?lang=unknown')->assertUnprocessable();
    }

    public function test_all_language_search_reads_every_catalog_page_before_its_own_pagination(): void
    {
        $this->ids('pages');
        for ($i = 0; $i < 103; $i++) {
            $id = 'catalog-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            Entry::make()->id($id)->collection('pages')->locale($i % 2 === 0 ? 'default' : 'english')
                ->slug($id)->published(true)->set('title', $id)->save();
        }
        $rows = [];
        foreach ([1, 2] as $page) {
            $part = $this->getJson('/api/socranext/v1/search-url?lang=all&per_page=100&page='.$page)->assertOk()
                ->assertHeader('X-WP-Total', '105')->assertHeader('X-WP-TotalPages', '2')->json();
            $this->assertCount($page === 1 ? 100 : 5, $part);
            array_push($rows, ...$part);
        }
        $this->assertCount(105, array_unique(array_column($rows, 'id')));
        $this->assertEqualsCanonicalizing(['default', 'english'], array_values(array_unique(array_column($rows, 'site'))));
    }
}
