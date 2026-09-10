<?php

namespace SocraNext\Statamic\Tests;

use SocraNext\Statamic\Content\{ContentRepository, IdentityMap};
use SocraNext\Statamic\Support\Connection;
use Statamic\Facades\{Collection, Entry, Site, Taxonomy, Term};

class AutomaticDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['socranext.content.discovery' => 'automatic']);
        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('a', 64));
        $this->withHeader('x-socranext-token', str_repeat('a', 64));
    }

    private function entry(string $collection, string $slug = 'public', array $data = [], bool $published = true, string $site = 'default')
    {
        if (!Collection::find($collection)) Collection::make($collection)->routes('/'.$collection.'/{slug}')->save();
        $entry = Entry::make()->id($collection.'-'.$site.'-'.$slug)->collection($collection)
            ->locale($site)->slug($slug)->published($published)->data(['title' => ucfirst($slug), ...$data]);
        $entry->save();
        return $entry;
    }

    public function test_unmapped_public_collection_is_discovered_searched_and_resolved_without_configuration(): void
    {
        $entry = $this->entry('case_studies');
        $this->getJson('/api/socranext/v1/post-types')->assertOk()
            ->assertJsonPath('postTypes.0.name', 'case_studies');
        $item = $this->getJson('/api/socranext/v1/cpt/case_studies/posts')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.post_type', 'case_studies')->assertJsonPath('0.resource_type', 'custom')->json()[0];
        $this->getJson('/api/socranext/v1/cpt/case_studies/posts/'.$item['id'])->assertOk()->assertJsonPath('native_id', $entry->id());
        $this->getJson('/api/socranext/v1/search-url?search='.rawurlencode($entry->absoluteUrl()))->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.id', $item['id']);
        $questions = [['question' => 'Was this discovered automatically?', 'answer' => '<p>Yes.</p>']];
        $this->postJson('/api/socranext/v1/cpt/case_studies/store-aio-information', ['page_id' => $item['id'], 'questions' => $questions])
            ->assertOk();
        $this->getJson('/api/socranext/v1/cpt/case_studies/aio-information/'.$item['id'])->assertOk()->assertJsonPath('questions', $questions);
        $this->getJson('/api/socranext/v1/translations?id='.$item['id'].'&kind=post&type=case_studies')->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.id', $item['id']);
    }

    public function test_configured_types_take_precedence_and_configured_mode_keeps_its_allowlist(): void
    {
        config(['socranext.content.collections' => ['pages' => ['landing'], 'posts' => ['updates'], 'products' => ['catalog'], 'custom' => ['guides']]]);
        foreach (['landing', 'updates', 'catalog', 'guides', 'case_studies'] as $handle) $this->entry($handle);
        foreach (['pages' => 'page', 'posts' => 'post', 'products' => 'product'] as $endpoint => $type) {
            $this->getJson('/api/socranext/v1/'.$endpoint)->assertOk()->assertJsonCount(1)->assertJsonPath('0.post_type', $type);
        }
        $this->assertSame(['guides', 'case_studies'], app(ContentRepository::class)->customCollections());
        foreach (['landing', 'updates', 'catalog'] as $handle) $this->getJson('/api/socranext/v1/cpt/'.$handle.'/posts')->assertNotFound();
        config(['socranext.content.discovery' => 'configured']);
        $this->getJson('/api/socranext/v1/cpt/guides/posts')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/socranext/v1/cpt/case_studies/posts')->assertNotFound();
        $this->getJson('/api/socranext/v1/search-url?search=case_studies')->assertOk()->assertExactJson([]);
    }

    public function test_drafts_private_dates_protected_and_redirect_entries_never_enter_public_discovery(): void
    {
        $public = $this->entry('stories');
        $draft = $this->entry('stories', 'draft', [], false);
        $protected = $this->entry('stories', 'members', ['protect' => 'logged_in', 'content' => 'Private text']);
        $redirect = $this->entry('stories', 'redirect', ['redirect' => 'https://example.com/elsewhere']);
        Collection::make('events')->routes('/events/{slug}')->dated(true)->futureDateBehavior('private')->save();
        $future = $this->entry('events', 'future');
        $future->date(now()->addDay())->save();
        $this->getJson('/api/socranext/v1/cpt/stories/posts')->assertOk()->assertJsonCount(1)->assertJsonPath('0.native_id', $public->id());
        $this->getJson('/api/socranext/v1/cpt/events/posts')->assertOk()->assertExactJson([]);
        $this->getJson('/api/socranext/v1/post-types')->assertOk()->assertJsonCount(1, 'postTypes')->assertJsonPath('postTypes.0.name', 'stories');
        foreach ([$draft, $protected, $redirect, $future] as $entry) {
            $this->getJson('/api/socranext/v1/search-url?search='.rawurlencode($entry->absoluteUrl()))->assertOk()->assertExactJson([]);
            // A previously assigned numeric ID is not permission to read newly hidden content.
            $id = app(ContentRepository::class)->identity($entry);
            $this->getJson('/api/socranext/v1/cpt/'.$entry->collectionHandle().'/posts/'.$id)->assertNotFound();
            $this->postJson('/api/socranext/v1/cpt/'.$entry->collectionHandle().'/store-aio-information', ['page_id' => $id, 'questions' => []])->assertNotFound();
        }
    }

    public function test_existing_configured_pages_honor_entry_collection_and_global_protection(): void
    {
        config(['socranext.content.discovery' => 'configured']);
        $public = $this->entry('pages');
        $protected = $this->entry('pages', 'secret', ['protect' => 'password']);
        $id = app(ContentRepository::class)->identity($protected);
        $this->getJson('/api/socranext/v1/pages')->assertOk()->assertJsonCount(1)->assertJsonPath('0.native_id', $public->id());
        $this->getJson('/api/socranext/v1/pages/'.$id)->assertNotFound();
        Collection::find('pages')->cascade(['protect' => 'logged_in'])->save();
        $this->getJson('/api/socranext/v1/pages')->assertOk()->assertExactJson([]);
        Collection::find('pages')->cascade([])->save();
        config(['statamic.protect.default' => 'logged_in']);
        $this->getJson('/api/socranext/v1/pages')->assertOk()->assertExactJson([]);
        $this->getJson('/api/socranext/v1/search-url')->assertOk()->assertExactJson([]);
    }

    public function test_unrouted_collections_and_own_archive_are_not_automatic_custom_types(): void
    {
        Collection::make('snippets')->save();
        $snippet = $this->entry('snippets');
        $archive = $this->entry('socranext_archives');
        $managed = $this->entry('socranext_articles', 'managed', ['socranext_owned' => true]);
        foreach (['snippets', 'socranext_archives', 'socranext_articles'] as $handle) $this->getJson('/api/socranext/v1/cpt/'.$handle.'/posts')->assertNotFound();
        $this->getJson('/api/socranext/v1/post-types')->assertOk()->assertJsonCount(1, 'postTypes')->assertJsonPath('postTypes.0.name', 'socranext_post');
        $this->getJson('/api/socranext/v1/cpt/socranext_post/posts')->assertOk()->assertJsonCount(1)->assertJsonPath('0.native_id', $managed->id());
        $this->getJson('/api/socranext/v1/search-url?search=socranext_archives')->assertOk()->assertExactJson([]);
        config(['socranext.content.collections.custom' => ['snippets']]);
        $this->getJson('/api/socranext/v1/cpt/snippets/posts')->assertOk()->assertExactJson([]);
        $id = app(ContentRepository::class)->identity($snippet);
        $this->getJson('/api/socranext/v1/cpt/snippets/posts/'.$id)->assertNotFound();
    }

    public function test_site_scoping_and_native_route_disable_apply_to_all_discovery_paths(): void
    {
        Site::setSites(['default' => ['name' => 'English', 'locale' => 'en_US', 'url' => 'https://example.com/'],
            'nl' => ['name' => 'Dutch', 'locale' => 'nl_NL', 'url' => 'https://example.com/nl/']]);
        config(['statamic.system.multisite' => true]);
        Collection::make('stories')->sites(['default', 'nl'])->routes('/stories/{slug}')->save();
        $entry = $this->entry('stories');
        $nl = $entry->makeLocalization('nl')->slug('nederlands')->set('title', 'Nederlands')->published(true);
        $nl->save();
        $id = app(ContentRepository::class)->identity($entry);
        $nlId = app(IdentityMap::class)->id('entry', $nl->id(), 'nl');
        $this->getJson('/api/socranext/v1/cpt/stories/posts/'.$nlId)->assertNotFound();
        $this->getJson('/api/socranext/v1/cpt/stories/posts?site=nl')->assertUnprocessable();
        $this->getJson('/api/socranext/v1/translations?id='.$id.'&kind=post&type=stories')->assertOk()->assertJsonCount(1);
        config(['socranext.content.sites' => ['default', 'nl']]);
        $this->getJson('/api/socranext/v1/cpt/stories/posts?site=nl')->assertOk()->assertJsonCount(1)->assertJsonPath('0.native_id', $nl->id());
        $nl->set('protect', 'password')->save();
        $this->getJson('/api/socranext/v1/translations?id='.$id.'&kind=post&type=stories')->assertOk()->assertJsonCount(1);
        config(['statamic.routes.enabled' => false]);
        $this->getJson('/api/socranext/v1/search-url')->assertOk()->assertExactJson([]);
        $this->getJson('/api/socranext/v1/cpt/stories/posts/'.$id)->assertNotFound();
    }

    public function test_protected_and_unlocalized_taxonomy_content_is_not_discovered(): void
    {
        config(['socranext.content.taxonomies' => ['topics']]);
        Taxonomy::make('topics')->save();
        $term = Term::make()->taxonomy('topics')->slug('secret')->data(['title' => 'Secret', 'protect' => 'password']);
        $term->save();
        $id = app(ContentRepository::class)->identity($term->in('default'));
        $this->getJson('/api/socranext/v1/categories')->assertOk()->assertExactJson([]);
        $this->getJson('/api/socranext/v1/categories/'.$id)->assertNotFound();
        $this->getJson('/api/socranext/v1/translations?id='.$id.'&kind=term&type=category')->assertNotFound();
    }

    public function test_automatic_enumeration_does_not_assign_identities_or_surface_reserved_platform_types(): void
    {
        $this->entry('case_studies');
        foreach (['page', 'post', 'product', 'category', 'socranext_post'] as $handle) $this->entry($handle);
        $before = file_get_contents(config('socranext.state_path'));
        $this->assertSame(['case_studies'], app(ContentRepository::class)->customCollections());
        $this->assertSame($before, file_get_contents(config('socranext.state_path')));
    }

    public function test_managed_draft_editing_remains_available_while_public_lookup_is_hidden(): void
    {
        $entry = $this->entry('socranext_articles', 'draft', ['socranext_owned' => true], false);
        $content = app(ContentRepository::class);
        $id = $content->identity($entry);
        $this->assertSame($entry->id(), $content->managed($id)->id());
        $this->getJson('/api/socranext/v1/cpt/socranext_post/posts/'.$id)->assertNotFound();
        $this->getJson('/api/socranext/v1/cpt/socranext_post/posts')->assertOk()->assertExactJson([]);
    }

    public function test_metadata_draft_exception_requires_explicit_configuration_and_never_bypasses_protection(): void
    {
        $entry = $this->entry('case_studies', 'draft', ['content' => 'Original draft'], false);
        $id = app(ContentRepository::class)->identity($entry);
        $payload = ['type' => 'custom', 'cpt' => 'case_studies', 'metaDescription' => 'Draft metadata'];
        $this->patchJson('/api/socranext/v1/metadata/'.$id, $payload)->assertNotFound();
        config(['socranext.content.collections.custom' => ['case_studies']]);
        $this->patchJson('/api/socranext/v1/metadata/'.$id, $payload)->assertOk()->assertJsonPath('published', false);
        $this->assertSame('Original draft', Entry::find($entry->id())->get('content'));
        $this->getJson('/api/socranext/v1/cpt/case_studies/posts/'.$id)->assertNotFound();
        $entry->set('protect', 'logged_in')->save();
        $this->patchJson('/api/socranext/v1/metadata/'.$id, $payload)->assertNotFound();
        $entry->remove('protect')->save();
        config(['statamic.protect.default' => 'password']);
        $this->patchJson('/api/socranext/v1/metadata/'.$id, $payload)->assertNotFound();
    }
}
