<?php

namespace SocraNext\Statamic\Tests;

use Statamic\Facades\{Blueprint, Collection, Entry};

class ConsoleTest extends TestCase
{
    public function test_install_is_registered_idempotent_and_preserves_existing_content(): void
    {
        Blueprint::setDirectory($this->temporaryDirectory.'/blueprints');
        Collection::make('pages')->routes('/{slug}')->save();
        Entry::make()->id('customer')->collection('pages')->slug('home')->set('title', 'Customer')->save();
        $this->artisan('socranext:install')->assertSuccessful();
        $this->artisan('socranext:install')->assertSuccessful();
        $this->assertSame(1, Entry::query()->where('collection', 'socranext_archives')->count());
        $this->assertSame('Customer', Entry::find('customer')->get('title'));
        $this->assertNotNull(Collection::find('socranext_articles'));
        $this->artisan('socranext:doctor', ['--json' => true])->expectsOutputToContain('"ready": false')->assertFailed();
    }
}
