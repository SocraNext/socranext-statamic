<?php

namespace SocraNext\Statamic\Support;

class Readiness
{
    public function __construct(private StateStore $store) {}

    public function ready(): bool
    {
        if (!(config('socranext.frontend_ready') || $this->store->get('frontend_ready', false))) return false;
        try {
            app(\SocraNext\Statamic\Content\ContentRepository::class)->assertSiteConfiguration();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return false;
        }
        return true;
    }
}
