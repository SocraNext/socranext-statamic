<?php

namespace SocraNext\Statamic\Tests;

use SocraNext\Statamic\Content\{ArticlePublisher, ContentRepository, IdentityMap};
use SocraNext\Statamic\Support\{Connection, StateStore};
use Statamic\Facades\{Blueprint, Collection, Entry};

class ContentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Blueprint::setDirectory($this->temporaryDirectory.'/blueprints');
        config(['statamic.revisions.enabled' => true]);
        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('f', 64));
        $this->withHeader('x-socranext-token', str_repeat('f', 64));
    }

    private function payload(array $extra = []): array
    {
        return [...['blogId' => 'article-123', 'titel' => 'Hello world', 'tekst' => '<p>Text<script>bad()</script></p>', 'slug' => 'hello'], ...$extra];
    }

    public function test_numeric_identity_is_deterministic_site_scoped_and_never_recycled(): void
    {
        $map = app(IdentityMap::class);
        $id = $map->id('entry', 'native-a', 'default');
        $this->assertLessThan(9007199254740991, $id);
        $this->assertNotSame($id, $map->id('entry', 'native-a', 'nl'));
        $map->tombstone($id);
        $this->assertSame($id, $map->id('entry', 'native-a', 'default'));
        $this->assertTrue($map->get($id)['deleted']);
        app(StateStore::class)->forget('identities');
        $this->assertSame($id, $map->id('entry', 'native-a', 'default'));
        $this->assertNotSame($id, $map->id('entry', 'native-b', 'default'));
    }

    public function test_publication_is_native_idempotent_and_detects_editor_conflicts(): void
    {
        $first = $this->postJson('/api/socranext/v1/blog', $this->payload())->assertOk()->json();
        $entry = Entry::find($first['native_id']);
        $this->assertSame('Hello world', $entry->get('title'));
        $this->assertStringNotContainsString('<script>', $entry->get('content'));
        $this->assertTrue($entry->published());
        $this->assertStringEndsWith('/artikelen-sn/hello', $first['url']);
        $this->postJson('/api/socranext/v1/blog', $this->payload())->assertOk()->assertJsonPath('id', $first['id']);
        $this->assertSame(1, Entry::query()->where('collection', 'socranext_articles')->count());
        $entry->set('title', 'Human edit')->save();
        $this->postJson('/api/socranext/v1/blog', $this->payload(['titel' => 'Overwrite']))->assertConflict();
        $this->assertSame('Human edit', Entry::find($first['native_id'])->get('title'));
        $this->patchJson('/api/socranext/v1/blog/meta/'.$first['id'], ['metaDescription' => 'Approved change', 'expected_revision' => app(ContentRepository::class)->fingerprint($entry)])
            ->assertOk();
        $this->assertSame('Human edit', Entry::find($first['native_id'])->get('title'));
    }

    public function test_draft_uses_native_working_copy_without_modifying_live_entry(): void
    {
        $published = $this->postJson('/api/socranext/v1/blog', $this->payload())->assertOk()->json();
        $this->postJson('/api/socranext/v1/blog', $this->payload(['titel' => 'Draft title', 'status' => 'draft']))
            ->assertOk()->assertJsonPath('working_copy', true);
        $entry = Entry::find($published['native_id']);
        $this->assertSame('Hello world', $entry->get('title'));
        $this->assertTrue($entry->published());
        $this->assertSame('Draft title', $entry->fromWorkingCopy()->get('title'));
        $this->postJson('/api/socranext/v1/blog', $this->payload(['titel' => 'Draft title']))->assertOk();
        $this->assertSame('Draft title', Entry::find($published['native_id'])->get('title'));
        $this->assertFalse(Entry::find($published['native_id'])->hasWorkingCopy());
    }

    public function test_discovery_paginates_selected_published_entries_and_protects_customer_content(): void
    {
        Collection::make('pages')->routes('/{slug}')->save();
        foreach (['a', 'b', 'c'] as $slug) Entry::make()->collection('pages')->id('page-'.$slug)->slug($slug)->set('title', strtoupper($slug))->published($slug !== 'c')->save();
        Collection::make('private')->routes('/private/{slug}')->save();
        Entry::make()->collection('private')->id('private-one')->slug('hidden')->published(true)->save();
        $list = $this->getJson('/api/socranext/v1/pages?per_page=1&page=2')->assertOk()->assertHeader('X-WP-Total', '2')->assertHeader('X-WP-TotalPages', '2')->json();
        $this->assertCount(1, $list);
        $this->assertSame('B', $list[0]['title']);
        $this->getJson('/api/socranext/v1/pages/'.$list[0]['id'])->assertOk();
        $this->deleteJson('/api/socranext/v1/blog/'.$list[0]['id'])->assertNotFound();
        $this->assertNotNull(Entry::find('page-b'));
        $this->getJson('/api/socranext/v1/search-url?search=hidden')->assertOk()->assertExactJson([]);
    }

    public function test_category_is_native_and_cannot_be_deleted_while_referenced(): void
    {
        $category = $this->postJson('/api/socranext/v1/blog-category', ['name' => 'News'])->assertOk()->json();
        $again = $this->postJson('/api/socranext/v1/blog-category', ['name' => 'News'])->assertOk()->json();
        $this->assertSame($category['id'], $again['id']);
        $article = $this->postJson('/api/socranext/v1/blog', $this->payload(['categorie' => $category['id']]))->assertOk()->json();
        $this->assertSame(['news'], Entry::find($article['native_id'])->get('socranext_categories'));
        $this->deleteJson('/api/socranext/v1/blog-category/'.$category['id'])->assertConflict();
        $this->deleteJson('/api/socranext/v1/blog/'.$article['id'])->assertOk();
        $this->deleteJson('/api/socranext/v1/blog-category/'.$category['id'])->assertOk();
        $this->assertTrue(app(IdentityMap::class)->get($article['id'])['deleted']);
        $this->postJson('/api/socranext/v1/blog', $this->payload())->assertConflict();
    }

    public function test_purge_then_explicit_restore_reuses_the_ids_and_leaves_customer_content(): void
    {
        Collection::make('pages')->routes('/{slug}')->save();
        $customer = Entry::make()->collection('pages')->id('customer')->slug('customer')->published(true)->set('title', 'Customer');
        $customer->save();
        $category = $this->postJson('/api/socranext/v1/blog-category', ['name' => 'News'])->assertOk()->json();
        $article = $this->postJson('/api/socranext/v1/blog', $this->payload(['categorie' => $category['id']]))->assertOk()->json();
        app(StateStore::class)->put('faqs', ['arbitrary' => ['questions' => ['remove']]]);
        $this->postJson('/api/socranext/v1/purge')->assertOk()->assertJsonPath('deleted', 1)->assertJsonPath('deleted_categories', 1);
        $this->assertNotNull(Entry::find('customer'));
        $this->assertNull(Entry::find($article['native_id']));
        $this->assertSame([], app(StateStore::class)->get('faqs', []));
        $this->postJson('/api/socranext/v1/blog', $this->payload())->assertConflict();
        $category2 = $this->postJson('/api/socranext/v1/blog-category', ['name' => 'News'])->assertOk()->json();
        $this->assertSame($category['id'], $category2['id']);
        $this->postJson('/api/socranext/v1/blog', $this->payload(['categorie' => $category2['id'], 'restore' => true]))->assertOk()
            ->assertJsonPath('id', $article['id'])->assertJsonPath('native_id', $article['native_id']);
    }

    public function test_native_saved_revision_recovers_a_lost_journal_without_ignoring_human_edits(): void
    {
        $result = $this->postJson('/api/socranext/v1/blog', $this->payload())->assertOk()->json();
        app(StateStore::class)->forget('article_sync');
        app(StateStore::class)->forget('article_bindings');
        $this->postJson('/api/socranext/v1/blog', $this->payload())->assertOk()->assertJsonPath('id', $result['id']);
        $this->assertSame(1, Entry::query()->where('collection', 'socranext_articles')->count());
        $entry = Entry::find($result['native_id']);
        $entry->set('title', 'Edited after crash')->save();
        app(StateStore::class)->forget('article_sync');
        $this->postJson('/api/socranext/v1/blog', $this->payload())->assertConflict();
    }

    public function test_archive_and_article_slugs_move_natively_and_record_redirects(): void
    {
        $article = $this->postJson('/api/socranext/v1/blog', $this->payload(['parentSlug' => 'news']))->assertOk()->json();
        $this->assertStringEndsWith('/artikelen-sn/news/hello', $article['url']);
        $this->postJson('/api/socranext/v1/collection-slugs', ['articles_slug' => 'insights'])->assertOk();
        $entry = Entry::find($article['native_id']);
        $this->assertStringEndsWith('/insights/news/hello', $entry->absoluteUrl());
        $archive = Entry::query()->where('collection', 'socranext_archives')->first();
        $this->assertSame('insights', $archive->slug());
        $redirects = app(StateStore::class)->get('redirects');
        $this->assertSame($entry->absoluteUrl(), $redirects[$article['url']]);
        $this->postJson('/api/socranext/v1/collection-slugs', ['articles_slug' => 'latest'])->assertOk();
        $redirects = app(StateStore::class)->get('redirects');
        $this->assertStringEndsWith('/latest/news/hello', $redirects[$article['url']]);
    }

    public function test_translations_use_native_origin_and_independent_site_identities(): void
    {
        \Statamic\Facades\Site::setSites([
            'default' => ['name' => 'English', 'locale' => 'en_US', 'url' => 'https://example.com/'],
            'nl' => ['name' => 'Nederlands', 'locale' => 'nl_NL', 'url' => 'https://example.com/nl/'],
        ]);
        config(['socranext.content.sites' => ['default', 'nl'], 'statamic.system.multisite' => true]);
        $this->getJson('/api/socranext/v1/languages')->assertOk()->assertJsonPath('1.code', 'nl');
        $source = $this->postJson('/api/socranext/v1/blog', $this->payload())->assertOk()->json();
        $translation = $this->postJson('/api/socranext/v1/blog', $this->payload(['blogId' => 'article-nl', 'language' => 'nl', 'sourcePostId' => $source['id'], 'titel' => 'Hallo wereld']))->assertOk()->json();
        $this->assertNotSame($source['id'], $translation['id']);
        $this->assertSame($source['native_id'], Entry::find($translation['native_id'])->origin()->id());
        $this->getJson('/api/socranext/v1/translations?id='.$source['id'].'&kind=post&type=socranext_post')->assertOk()->assertJsonCount(2);
        $category = $this->postJson('/api/socranext/v1/blog-category', ['name' => 'News'])->assertOk()->json();
        $termNl = $this->postJson('/api/socranext/v1/blog-category', ['name' => 'Nieuws', 'language' => 'nl', 'sourceTermId' => $category['id']])->assertOk()->json();
        $this->assertNotSame($category['id'], $termNl['id']);
        $this->postJson('/api/socranext/v1/blog-category', ['name' => 'Replaced', 'language' => 'nl', 'sourceTermId' => $category['id']])->assertOk()->assertJsonPath('name', 'Nieuws');
        $this->deleteJson('/api/socranext/v1/blog-category/'.$termNl['id'])->assertOk();
        $this->assertSame('News', app(ContentRepository::class)->managedTerm($category['id'])->get('title'));
    }

    public function test_foreign_working_copy_and_disabled_revisions_are_explicit_conflicts(): void
    {
        $article = $this->postJson('/api/socranext/v1/blog', $this->payload())->assertOk()->json();
        $entry = Entry::find($article['native_id']);
        $working = clone $entry;
        $working->set('title', 'Native draft')->makeWorkingCopy()->save();
        $this->postJson('/api/socranext/v1/blog', $this->payload(['titel' => 'Change']))->assertConflict();
        $entry->deleteWorkingCopy();
        config(['statamic.revisions.enabled' => false]);
        $this->postJson('/api/socranext/v1/blog', $this->payload(['status' => 'draft']))->assertUnprocessable();
        $this->assertSame('Hello world', Entry::find($article['native_id'])->get('title'));
    }

    public function test_metadata_patch_preserves_unpublished_and_working_copy_drafts(): void
    {
        $draft = $this->postJson('/api/socranext/v1/blog', $this->payload(['status' => 'draft']))->assertOk()->json();
        $this->patchJson('/api/socranext/v1/blog/meta/'.$draft['id'], ['metaDescription' => 'Draft description'])->assertOk()->assertJsonPath('draft', true);
        $this->assertFalse(Entry::find($draft['native_id'])->published());
        $this->postJson('/api/socranext/v1/blog', $this->payload())->assertOk();
        $this->postJson('/api/socranext/v1/blog', $this->payload(['status' => 'draft', 'titel' => 'Working title']))->assertOk();
        $this->patchJson('/api/socranext/v1/blog/meta/'.$draft['id'], ['metaDescription' => 'Working description'])->assertOk()->assertJsonPath('draft', true);
        $entry = Entry::find($draft['native_id']);
        $this->assertSame('Hello world', $entry->get('title'));
        $this->assertSame('Working title', $entry->fromWorkingCopy()->get('title'));
        $this->assertSame('Working description', $entry->fromWorkingCopy()->get('socranext_meta_description'));
    }

    public function test_incomplete_publication_journal_never_overwrites_native_content_after_a_crash(): void
    {
        $article = $this->postJson('/api/socranext/v1/blog', $this->payload())->assertOk()->json();
        app(StateStore::class)->transaction(function (array &$data) {
            foreach ($data['article_bindings'] as &$binding) $binding['complete'] = false;
            unset($data['article_sync']);
        });
        Entry::find($article['native_id'])->set('title', 'Human after crash')->save();
        $this->postJson('/api/socranext/v1/blog', $this->payload())->assertConflict();
        $this->assertSame('Human after crash', Entry::find($article['native_id'])->get('title'));
    }

    public function test_individual_slug_change_rejects_collision_and_records_actual_redirect(): void
    {
        $article = $this->postJson('/api/socranext/v1/blog', $this->payload())->assertOk()->json();
        Collection::make('pages')->routes('/artikelen-sn/{slug}')->save();
        Entry::make()->collection('pages')->id('customer-occupied')->slug('occupied')->published(true)->set('title', 'Existing')->save();
        $this->patchJson('/api/socranext/v1/blog/meta/'.$article['id'], ['slug' => 'occupied'])->assertConflict();
        $this->patchJson('/api/socranext/v1/blog/meta/'.$article['id'], ['slug' => 'new-name'])->assertOk();
        $newUrl = Entry::find($article['native_id'])->absoluteUrl();
        $this->assertStringEndsWith('/artikelen-sn/new-name', $newUrl);
        $this->assertSame($newUrl, app(StateStore::class)->get('redirects')[$article['url']]);
    }
}
