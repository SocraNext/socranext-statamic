<?php

namespace SocraNext\Statamic\Support;

class Readiness
{
    public function __construct(private StateStore $store) {}

    public function ready(): bool
    {
        return (bool) (config('socranext.frontend_ready') || $this->store->get('frontend_ready', false));
    }
}
