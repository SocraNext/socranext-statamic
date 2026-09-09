<?php

namespace SocraNext\Statamic\Tags;

use SocraNext\Statamic\Rendering\Renderer;
use Statamic\Facades\{Entry, Site, Term};
use Statamic\Tags\Tags;

class SocraNext extends Tags
{
    protected static $handle = 'socranext';

    public function faq(): string
    {
        $id = $this->params->get('id', $this->context->get('id'));
        return $id ? app(Renderer::class)->faq((string) $id) : '';
    }

    public function article(): string
    {
        $id = $this->params->get('id', $this->context->get('id'));
        return $id ? app(Renderer::class)->article(Entry::find((string) $id)) : '';
    }

    public function articles(): string
    {
        return app(Renderer::class)->articles();
    }

    public function metadata(): string
    {
        $id = $this->params->get('id', $this->context->get('id'));
        if (! $id) return '';
        $resource = Entry::find((string) $id) ?? Term::find((string) $id)?->in(Site::current()->handle());
        return app(Renderer::class)->metadata($resource);
    }
}
