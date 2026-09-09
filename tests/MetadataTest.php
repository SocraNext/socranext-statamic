<?php

namespace SocraNext\Statamic\Tests;

use SocraNext\Statamic\Content\ContentRepository;
use SocraNext\Statamic\Rendering\Renderer;
use SocraNext\Statamic\Support\{Connection, StateStore};
use Statamic\Facades\{Collection, Entry, Taxonomy, Term};

class MetadataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('f', 64));
        $this->withHeader('x-socranext-token', str_repeat('f', 64));
        config(['statamic.revisions.enabled' => true]);
    }

    public function test_native_metadata_preserves_content_and_status_and_creates_redirects(): void
    {
        Collection::make('pages')->routes('/{slug}')->revisionsEnabled(true)->save();
        $entry = Entry::make()->id('native-page')->collection('pages')->slug('before')->published(false)->data(['title' => 'Before', 'blocks' => [['type' => 'customer']]]);
        $entry->save();
        $oldUrl = $entry->absoluteUrl();
        $id = app(ContentRepository::class)->identity($entry);
        $this->patchJson('/api/socranext/v1/metadata/'.$id, ['type' => 'pages', 'title' => 'After', 'slug' => 'after', 'metaDescription' => 'Description'])
            ->assertOk()->assertJsonPath('id', $id)->assertJsonPath('title', 'After')->assertJsonPath('published', false);
        $current = Entry::find($entry->id());
        $this->assertSame([['type' => 'customer']], $current->get('blocks'));
        $this->assertFalse($current->published());
        $this->assertSame('Description', app(StateStore::class)->get('metadata')[$id]['meta_description']);
        $this->assertSame($current->absoluteUrl(), app(StateStore::class)->get('redirects')[$oldUrl]);
        $current->set('title', 'Edited in Statamic')->save();
        $this->patchJson('/api/socranext/v1/metadata/'.$id, ['type' => 'pages', 'title' => 'Overwrite'])->assertConflict();
        $this->patchJson('/api/socranext/v1/metadata/'.$id, ['type' => 'pages', 'title' => 'Reconciled', 'expected_revision' => app(ContentRepository::class)->fingerprint($current)])->assertOk();
    }

    public function test_metadata_rejects_unexposed_content_and_url_collisions(): void
    {
        Collection::make('pages')->routes('/{slug}')->save();
        $entry = Entry::make()->id('a')->collection('pages')->slug('a')->set('title', 'A')->published(true);
        $entry->save();
        Entry::make()->id('b')->collection('pages')->slug('b')->set('title', 'B')->published(true)->save();
        $id = app(ContentRepository::class)->identity($entry);
        $this->patchJson('/api/socranext/v1/metadata/'.$id, ['type' => 'products', 'title' => 'Wrong type'])->assertNotFound();
        $this->patchJson('/api/socranext/v1/metadata/'.$id, ['type' => 'pages', 'slug' => 'b'])->assertConflict();
        $this->assertSame('a', Entry::find('a')->slug());
    }

    public function test_category_metadata_preserves_identity_and_rejects_unsafe_slug_changes(): void
    {
        Taxonomy::make('topics')->save();
        config(['socranext.content.taxonomies' => ['topics']]);
        $term = Term::make()->taxonomy('topics')->slug('news')->data(['title' => 'News']);
        $term->save();
        $localized = $term->in('default');
        $id = app(ContentRepository::class)->identity($localized);
        $this->patchJson('/api/socranext/v1/metadata/'.$id, ['type' => 'categories', 'title' => 'Updates', 'slug' => 'news', 'metaDescription' => 'Latest'])
            ->assertOk()->assertJsonPath('id', $id)->assertJsonPath('title', 'Updates');
        $this->patchJson('/api/socranext/v1/metadata/'.$id, ['type' => 'categories', 'slug' => 'updates'])->assertUnprocessable();
        $this->assertNotNull(Term::find('topics::news'));
    }
}
