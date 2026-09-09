<?php

namespace SocraNext\Statamic\Tests;

use SocraNext\Statamic\Content\ContentRepository;
use Statamic\Facades\{Blink, Blueprint, Collection, Entry, Site, Stache};

class FingerprintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Blueprint::setDirectory($this->temporaryDirectory.'/blueprints');
        Site::setSites([
            'default' => ['name' => 'English', 'locale' => 'en_US', 'url' => 'https://example.com/'],
            'nl' => ['name' => 'Dutch', 'locale' => 'nl_NL', 'url' => 'https://example.com/nl/'],
        ]);
        config(['statamic.system.multisite' => true, 'socranext.content.sites' => ['default', 'nl']]);
        Blueprint::make('article')->setNamespace('collections/fingerprint_pages')->setContents([
            'title' => 'Article', 'tabs' => ['main' => ['sections' => [['fields' => [
                ['handle' => 'title', 'field' => ['type' => 'text', 'localizable' => true]],
                ['handle' => 'content', 'field' => ['type' => 'textarea', 'localizable' => true]],
            ]]]]],
        ])->save();
        Collection::make('fingerprint_pages')->sites(['default', 'nl'])
            ->routes(['default' => '/{slug}', 'nl' => '/{slug}'])->save();
    }

    private function reload(string $id)
    {
        Stache::store('entries')->store('fingerprint_pages')->forgetItem($id);
        Blink::flush();
        return Entry::find($id);
    }

    public function test_root_fingerprint_matches_native_removal_of_empty_values_after_file_reload(): void
    {
        $entry = Entry::make()->collection('fingerprint_pages')->locale('default')->blueprint('article')
            ->slug('source')->published(true)->data([
                'title' => 'Source', 'content' => " \n<p>Body</p>\n", 'null_value' => null,
                'empty_text' => '', 'empty_list' => [], 'false_value' => false, 'zero_value' => 0,
                'nested' => ['null' => null, 'empty_text' => '', 'empty_list' => []],
            ]);
        $entry->save();
        $this->assertArrayNotHasKey('blueprint', $entry->data()->all());
        $hash = app(ContentRepository::class)->fingerprint($entry);
        $reloaded = $this->reload($entry->id());
        $this->assertNotSame($entry, $reloaded);
        $this->assertSame('article', $reloaded->data()->get('blueprint'));
        $this->assertSame('collections.fingerprint_pages', $reloaded->blueprint()->namespace());
        foreach (['null_value', 'empty_text', 'empty_list'] as $key) $this->assertArrayNotHasKey($key, $reloaded->data()->all());
        $this->assertFalse($reloaded->get('false_value'));
        $this->assertSame(0, $reloaded->get('zero_value'));
        $this->assertSame($entry->get('nested'), $reloaded->get('nested'));
        $this->assertSame(ltrim($entry->get('content')), $reloaded->get('content'));
        $this->assertSame($hash, app(ContentRepository::class)->fingerprint($reloaded));

        $reloaded->set('false_value', true)->save();
        $this->assertNotSame($hash, app(ContentRepository::class)->fingerprint($this->reload($entry->id())));
    }

    public function test_translation_fingerprint_keeps_explicit_empty_overrides_and_inherited_blueprint_after_reload(): void
    {
        $root = Entry::make()->collection('fingerprint_pages')->locale('default')->blueprint('article')
            ->slug('source')->published(true)->data([
                'title' => 'Source', 'null_value' => 'Root value', 'empty_text' => 'Root text',
                'empty_list' => ['root'], 'inherited_text' => 'Inherited content',
            ]);
        $root->save();
        $translation = $root->makeLocalization('nl')->blueprint('article')->slug('vertaling')->data([
            'title' => 'Vertaling', 'null_value' => null, 'empty_text' => '', 'empty_list' => [],
            'false_value' => false, 'zero_value' => 0,
        ]);
        $translation->save();
        $rootHash = app(ContentRepository::class)->fingerprint($root);
        $translationHash = app(ContentRepository::class)->fingerprint($translation);
        $reloadedRoot = $this->reload($root->id());
        $reloaded = $this->reload($translation->id());
        $this->assertNotSame($translation, $reloaded);
        $this->assertSame($root->id(), $reloaded->origin()->id());
        $this->assertSame('nl', $reloaded->locale());
        $this->assertArrayNotHasKey('blueprint', $reloaded->data()->all());
        $this->assertSame('article', $reloaded->blueprint()->handle());
        $this->assertSame('collections.fingerprint_pages', $reloaded->blueprint()->namespace());
        foreach (['null_value' => null, 'empty_text' => '', 'empty_list' => [], 'false_value' => false, 'zero_value' => 0] as $key => $value) {
            $this->assertArrayHasKey($key, $reloaded->data()->all());
            $this->assertSame($value, $reloaded->value($key));
        }
        $this->assertSame('Inherited content', $reloaded->value('inherited_text'));
        $this->assertSame($rootHash, app(ContentRepository::class)->fingerprint($reloadedRoot));
        $this->assertSame($translationHash, app(ContentRepository::class)->fingerprint($reloaded));

        $reloaded->data($reloaded->data()->except('null_value'));
        $this->assertSame('Root value', $reloaded->value('null_value'));
        $this->assertNotSame($translationHash, app(ContentRepository::class)->fingerprint($reloaded));
        $inheritedHash = app(ContentRepository::class)->fingerprint($reloaded);
        $reloaded->save();
        $this->assertSame($inheritedHash, app(ContentRepository::class)->fingerprint($this->reload($translation->id())));
    }
}
