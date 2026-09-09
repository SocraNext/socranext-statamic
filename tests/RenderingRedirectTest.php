<?php

namespace SocraNext\Statamic\Tests;

use SocraNext\Statamic\Content\ArticlePublisher;
use SocraNext\Statamic\Rendering\PreviewSession;
use SocraNext\Statamic\Support\{Connection, StateStore};
use Statamic\Facades\{Blueprint, Collection, Entry};

class RenderingRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Statamic\Facades\Site::setSites(['default' => ['name' => 'Website', 'locale' => 'en_US', 'url' => 'https://example.com/']]);
        Blueprint::setDirectory($this->temporaryDirectory.'/blueprints');
        config(['statamic.revisions.enabled' => true]);
        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('d', 64));
        $this->withHeader('x-socranext-token', str_repeat('d', 64));
        $views = $this->temporaryDirectory.'/views';
        mkdir($views);
        file_put_contents($views.'/layout.antlers.html', '<!doctype html><html><body>{{ template_content }}</body></html>');
        file_put_contents($views.'/plain.antlers.html', '<h1>{{ title }}</h1>');
        view()->addNamespace('redirecttest', $views);
        config(['socranext.content.article_layout' => 'redirecttest::layout']);
    }

    private function publish(): array
    {
        return $this->postJson('/api/socranext/v1/blog', ['blogId' => 'redirect-article', 'titel' => 'Redirect article', 'tekst' => '<p>Native content</p>', 'slug' => 'original'])->assertOk()->json();
    }

    public function test_native_article_and_archive_renames_redirect_only_missing_public_urls(): void
    {
        $published = $this->publish();
        $oldArticle = $published['url'];
        $archiveId = app(StateStore::class)->get('archive_entries')['default'];
        $oldArchive = Entry::find($archiveId)->absoluteUrl();
        $this->get($oldArticle)->assertOk()->assertSee('Native content');
        $this->patchJson('/api/socranext/v1/blog/meta/'.$published['id'], ['slug' => 'renamed'])->assertOk();
        $newArticle = Entry::find($published['native_id'])->absoluteUrl();
        $this->assertSame($newArticle, app(StateStore::class)->get('redirects', [])[$oldArticle] ?? null);
        $this->assertContains(\SocraNext\Statamic\Http\Middleware\ManagedRedirect::class, app('router')->getMiddlewareGroups()['web']);
        $this->assertNotNull(\Statamic\Facades\Data::findByRequestUrl($newArticle));
        $this->get($oldArticle)->assertStatus(301)->assertHeader('Location', $newArticle);
        $this->call('HEAD', $oldArticle)->assertStatus(301)->assertHeader('Location', $newArticle);
        $this->post($oldArticle)->assertNotFound();
        $this->postJson('/api/socranext/v1/collection-slugs', ['articles_slug' => 'insights'])->assertOk();
        $latest = Entry::find($published['native_id'])->absoluteUrl();
        $latestArchive = Entry::find($archiveId)->absoluteUrl();
        $this->get($oldArticle)->assertStatus(301)->assertHeader('Location', $latest);
        $this->get($oldArchive)->assertStatus(301)->assertHeader('Location', $latestArchive);
        $this->get($latestArchive)->assertOk()->assertSee('Redirect article');
        $this->get($latest)->assertOk()->assertSee('Native content');
    }

    public function test_existing_native_page_wins_over_old_redirect_and_unsafe_targets_stay_404(): void
    {
        $published = $this->publish();
        Collection::make('replacement')->routes('/{slug}')->template('redirecttest::plain')->layout('redirecttest::layout')->save();
        Entry::make()->collection('replacement')->locale('default')->slug('existing')->published(true)->data(['title' => 'Existing page wins'])->save();
        $base = 'https://example.com';
        app(StateStore::class)->put('redirects', [
            $base.'/existing' => $published['url'],
            $base.'/missing' => 'https://evil.example/landing',
            $base.'/cycle-a' => $base.'/cycle-b',
            $base.'/cycle-b' => $base.'/cycle-a',
            $base.'/dead' => $base.'/no-longer-exists',
            'https://spoofed.example/old' => 'https://spoofed.example/existing',
        ]);
        $this->get($base.'/existing')->assertOk()->assertSee('Existing page wins');
        foreach (['missing', 'cycle-a', 'cycle-b', 'dead'] as $path) $this->get($base.'/'.$path)->assertNotFound();
        $this->get('https://spoofed.example/old')->assertNotFound();
    }

    public function test_disconnect_and_same_credential_reconnect_revoke_preview_sessions(): void
    {
        config(['socranext.preview_origins' => ['https://platform.socranext.ai']]);
        $session = app(PreviewSession::class)->issue('https://platform.socranext.ai');
        $url = '/socranext/preview/faq?token='.rawurlencode($session['token']);
        $this->get($url)->assertOk();
        $connection = app(Connection::class);
        $connection->disconnect();
        $this->get($url)->assertForbidden();
        $connection->receive($connection->begin(), str_repeat('d', 64));
        $this->get($url)->assertForbidden();
        $replacement = app(PreviewSession::class)->issue('https://platform.socranext.ai');
        $this->get('/socranext/preview/faq?token='.rawurlencode($replacement['token']))->assertOk();
    }
}
