<?php

namespace SocraNext\Statamic\Tests;

use SocraNext\Statamic\Content\ContentRepository;
use SocraNext\Statamic\Support\{Connection, StateStore};
use Statamic\Facades\{Site, Taxonomy, Term};

class CategoryDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('c', 64));
        $this->withHeader('x-socranext-token', str_repeat('c', 64));
        config(['socranext.content.taxonomies' => ['topics'], 'socranext.frontend.mode' => 'manual']);
        app(StateStore::class)->put('frontend_ready', true);
    }

    private function term()
    {
        Taxonomy::make('topics')->save();
        $term = Term::make()->taxonomy('topics')->slug('example')->data(['title' => 'Example category']);
        $term->save();
        return $term->in('default');
    }

    public function test_missing_public_template_excludes_categories_from_every_discovery_and_faq_path(): void
    {
        $term = $this->term();
        $id = app(ContentRepository::class)->identity($term);
        $this->get(parse_url($term->absoluteUrl(), PHP_URL_PATH), ['Accept' => 'text/html'])->assertNotFound();
        $this->getJson('/api/socranext/v1/categories')->assertOk()->assertExactJson([])->assertHeader('X-WP-Total', '0');
        $this->getJson('/api/socranext/v1/search-url')->assertOk()->assertExactJson([]);
        $this->getJson('/api/socranext/v1/post-types')->assertOk()->assertJsonPath('availability.categories', false);
        $this->getJson('/api/socranext/v1/categories/'.$id)->assertNotFound();
        $this->getJson('/api/socranext/v1/translations?id='.$id.'&kind=term&type=category')->assertNotFound();
        $this->getJson('/api/socranext/v1/category-aio-information/'.$id)->assertNotFound();
        $this->postJson('/api/socranext/v1/store-category-aio-information', ['page_id' => $id, 'questions' => []])->assertNotFound();
        $this->postJson('/api/socranext/v1/category-toggle', ['page_id' => $id, 'enabled' => true])->assertNotFound();
        $this->assertSame([], app(StateStore::class)->get('faqs', []));
    }

    public function test_real_public_category_keeps_its_identity_and_renders_its_selected_faq(): void
    {
        $term = $this->term();
        $id = app(ContentRepository::class)->identity($term);
        $this->publicTaxonomy('topics');
        $this->getJson('/api/socranext/v1/categories')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $id);
        $this->getJson('/api/socranext/v1/search-url')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $id);
        $this->getJson('/api/socranext/v1/post-types')->assertOk()->assertJsonPath('availability.categories', true);
        $this->postJson('/api/socranext/v1/store-category-aio-information', ['page_id' => $id,
            'questions' => [['question' => 'Does this category have a public FAQ?', 'answer' => 'Yes.']]])->assertOk();
        $this->postJson('/api/socranext/v1/category-toggle', ['page_id' => $id, 'enabled' => true])->assertOk();
        $this->get(parse_url($term->absoluteUrl(), PHP_URL_PATH), ['Accept' => 'text/html'])
            ->assertOk()->assertSee('Does this category have a public FAQ?');
    }

    public function test_internal_article_categories_remain_usable_without_becoming_public_faq_targets(): void
    {
        $category = $this->postJson('/api/socranext/v1/blog-category', ['name' => 'Internal label'])->assertOk()->json();
        $this->getJson('/api/socranext/v1/categories')->assertOk()->assertExactJson([]);
        $this->patchJson('/api/socranext/v1/metadata/'.$category['id'], ['type' => 'categories', 'title' => 'Updated label'])
            ->assertOk()->assertJsonPath('title', 'Updated label');
        $article = $this->postJson('/api/socranext/v1/blog', ['blogId' => 'category-discovery-regression',
            'titel' => 'An article with a category', 'tekst' => '<p>Article body.</p>', 'categorie' => $category['id']])
            ->assertOk()->assertJsonPath('success', true)->json();
        $this->assertSame($category['id'], app(ContentRepository::class)->identity(app(ContentRepository::class)->managedTerm($category['id'])));
        $this->get(parse_url($article['url'], PHP_URL_PATH), ['Accept' => 'text/html'])->assertOk()->assertSee('Article body.');
    }

    public function test_category_templates_are_resolved_for_each_native_site_without_changing_the_active_finder(): void
    {
        Site::setSites(['default' => ['name' => 'Dutch', 'locale' => 'nl_NL', 'url' => '/'],
            'english' => ['name' => 'English', 'locale' => 'en_GB', 'url' => '/en/']]);
        config(['statamic.system.multisite' => true, 'socranext.content.sites' => ['default', 'english']]);
        Taxonomy::make('topics')->sites(['default', 'english'])->save();
        $term = Term::make()->taxonomy('topics')->slug('example')->data(['title' => 'Dutch category']);
        $term->save();
        $english = $term->in('english')->data(['title' => 'English category']);
        $english->save();
        $englishId = app(ContentRepository::class)->identity($english);
        $views = $this->temporaryDirectory.'/site-views';
        mkdir($views.'/english/topics', 0755, true);
        file_put_contents($views.'/layout.antlers.html', '{{ template_content }}');
        file_put_contents($views.'/english/topics/show.blade.php', '<html><body>English category</body></html>');
        view()->prependLocation($views);
        Site::setCurrent('default');
        $paths = view()->getFinder()->getPaths();
        $this->getJson('/api/socranext/v1/categories?lang=all')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.id', $englishId)->assertJsonPath('0.site', 'english');
        $this->getJson('/api/socranext/v1/translations?id='.$englishId.'&kind=term&type=category')->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.id', $englishId);
        $this->assertSame($paths, view()->getFinder()->getPaths());
        $this->assertSame('default', Site::current()->handle());
        // Testbench reuses the facade between requests; emulate a new English request.
        Site::setCurrent('english');
        $this->get('/en/topics/example', ['Accept' => 'text/html'])->assertOk()->assertSee('English category');
    }
}
