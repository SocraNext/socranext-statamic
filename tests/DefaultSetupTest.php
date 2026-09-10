<?php

namespace SocraNext\Statamic\Tests;

use SocraNext\Statamic\Support\{Readiness, Setup};
use Statamic\Facades\{AssetContainer, Collection};

/** Fresh host setup without additional blueprint, URL, disk, or layout configuration. */
class DefaultSetupTest extends TestCase
{
    public function test_default_installation_prepares_a_ready_site_without_customer_configuration(): void
    {
        $this->assertFalse(app(Setup::class)->isComplete());
        $this->assertFalse(app(Readiness::class)->ready());
        $this->assertTrue(app(Setup::class)->prepare()['asset_container_created']);
        $this->assertTrue(app(Setup::class)->isComplete());
        $this->assertTrue(app(Readiness::class)->ready());
        $this->assertSame('socranext_public', AssetContainer::find('socranext')->diskHandle());
        $this->assertSame('socranext::public.layout', Collection::find('socranext_articles')->layout());
    }
}
