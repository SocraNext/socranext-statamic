<?php

namespace SocraNext\Statamic\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use SocraNext\Statamic\Content\ContentRepository;
use SocraNext\Statamic\Support\{Connection, StateStore};
use Statamic\Facades\{Collection, Entry, Taxonomy, Term};

class DiscoveryFaqContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('d', 64));
        $this->withHeader('x-socranext-token', str_repeat('d', 64));
        app(StateStore::class)->put('frontend_ready', true);
        config(['socranext.frontend.mode' => 'manual', 'socranext.content.collections' => [
            'pages' => ['pages', 'landing_pages'], 'posts' => ['news'],
            'products' => ['catalog'], 'custom' => ['guides'],
        ], 'socranext.content.taxonomies' => ['topics']]);
    }

    private function entry(string $collection)
    {
        Collection::make($collection)->routes('/'.$collection.'/{slug}')->save();
        $entry = Entry::make()->id('native-'.$collection)->collection($collection)
            ->locale('default')->slug('qa')->published(true)->set('title', 'QA '.$collection);
        $entry->save();
        return $entry;
    }

    private function faqRoundTrip(string $prefix, int $id): void
    {
        $question = ['question' => 'Can this native object receive its own FAQ?', 'answer' => '<p>Yes, through its discovered type.</p>'];
        $store = str_ends_with($prefix, '-') ? 'store-'.$prefix.'aio-information' : $prefix.'store-aio-information';
        $this->postJson('/api/socranext/v1/'.$store, ['page_id' => $id, 'questions' => [$question]])
            ->assertOk()->assertJsonPath('success', true);
        $this->postJson('/api/socranext/v1/'.$prefix.'toggle', ['page_id' => $id, 'enabled' => true])
            ->assertOk()->assertJsonPath('enabled', true);
        $this->getJson('/api/socranext/v1/'.$prefix.'aio-information/'.$id)->assertOk()
            ->assertJsonPath('enabled', true)->assertJsonPath('questions', [$question]);
    }

    public static function standardEntries(): array
    {
        return [
            'native pages collection' => ['pages', 'pages', 'pages', 'page', ''],
            'renamed page collection' => ['landing_pages', 'pages', 'pages', 'page', ''],
            'native news collection' => ['news', 'posts', 'blogs', 'post', 'post-'],
            'native catalog collection' => ['catalog', 'products', 'products', 'product', 'product-'],
        ];
    }

    #[DataProvider('standardEntries')]
    public function test_standard_discovery_uses_the_platform_type_and_round_trips_faqs(
        string $collection, string $endpoint, string $resourceType, string $postType, string $prefix,
    ): void {
        $entry = $this->entry($collection);
        $id = app(ContentRepository::class)->identity($entry);
        $this->getJson('/api/socranext/v1/'.$endpoint)->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.post_type', $postType)->assertJsonPath('0.resource_type', $resourceType)
            ->assertJsonPath('0.id', $id)->assertJsonPath('0.native_id', $entry->id());
        $this->getJson('/api/socranext/v1/'.$endpoint.'/'.$id)->assertOk()
            ->assertJsonPath('post_type', $postType)->assertJsonPath('site', 'default');
        $this->getJson('/api/socranext/v1/search-url?search='.rawurlencode($entry->absoluteUrl()))->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.post_type', $postType)->assertJsonPath('0.id', $id);
        $this->getJson('/api/socranext/v1/translations?id='.$id.'&kind=post&type='.$postType)->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.post_type', $postType)->assertJsonPath('0.id', $id);
        $this->faqRoundTrip($prefix, $id);
        // Correcting discovery must not expose standard collections via the custom allowlist.
        $this->postJson('/api/socranext/v1/cpt/'.$collection.'/store-aio-information', ['page_id' => $id, 'questions' => []])
            ->assertNotFound();
    }

    public function test_native_taxonomy_uses_category_contract_and_round_trips_its_faq(): void
    {
        Taxonomy::make('topics')->save();
        $term = Term::make()->taxonomy('topics')->slug('qa')->data(['title' => 'QA topic']);
        $term->save();
        $localized = $term->in('default');
        $id = app(ContentRepository::class)->identity($localized);
        $this->getJson('/api/socranext/v1/categories/'.$id)->assertOk()
            ->assertJsonPath('post_type', 'category')->assertJsonPath('resource_type', 'categories')
            ->assertJsonPath('id', $id)->assertJsonPath('native_id', $localized->id());
        $this->getJson('/api/socranext/v1/translations?id='.$id.'&kind=term&type=category')->assertOk()
            ->assertJsonPath('0.post_type', 'category')->assertJsonPath('0.id', $id);
        $this->faqRoundTrip('category-', $id);
    }

    public function test_custom_and_managed_collection_contracts_remain_custom(): void
    {
        foreach (['guides' => 'guides', 'socranext_articles' => 'socranext_post'] as $collection => $postType) {
            $entry = $this->entry($collection);
            $id = app(ContentRepository::class)->identity($entry);
            $this->getJson('/api/socranext/v1/cpt/'.$postType.'/posts')->assertOk()->assertJsonCount(1)
                ->assertJsonPath('0.post_type', $postType)->assertJsonPath('0.resource_type', 'custom')
                ->assertJsonPath('0.id', $id)->assertJsonPath('0.native_id', $entry->id());
            $this->faqRoundTrip('cpt/'.$postType.'/', $id);
        }
    }
}
