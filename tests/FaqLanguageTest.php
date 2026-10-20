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
        $this->postJson('/api/socranext/v1/toggle', ['page_id' => $uk, 'language' => 'en-GB', 'site_id' => 'default', 'enabled' => false])->assertOk();
        foreach (['en', 'en-US'] as $wrong) $this->postJson('/api/socranext/v1/toggle', ['page_id' => $uk, 'language' => $wrong, 'enabled' => false])->assertConflict();
        $this->getJson('/api/socranext/v1/translations?id='.$uk.'&type=page&language=en-US')->assertConflict();
    }
}
