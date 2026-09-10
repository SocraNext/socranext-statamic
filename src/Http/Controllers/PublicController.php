<?php

namespace SocraNext\Statamic\Http\Controllers;

use Illuminate\Http\Request;
use SocraNext\Statamic\Rendering\{Renderer, PreviewSession};
use SocraNext\Statamic\Support\StateStore;

class PublicController
{
    public function __construct(private StateStore $store, private Renderer $renderer, private PreviewSession $previews) {}

    public function llms(Request $request, bool $full = false)
    {
        $content = $this->store->get('llms', [])[$full ? 'llmsFullTxt' : 'llmsTxt'] ?? '';
        abort_if($content === '', 404);
        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function preview(Request $request, string $kind)
    {
        abort_unless(in_array($kind, ['faq','article','articles'], true), 404);
        $session = $this->previews->verify((string) $request->query('token', $request->query('socranext_token', '')));
        $entries = $this->renderer->publishedEntries();
        $body = match ($kind) { 'faq' => $this->renderer->faq(($entries[0] ?? null)?->id() ?? 0, true), 'article' => $this->renderer->article($entries[0] ?? null, true), default => $this->renderer->articles(true) };
        $data = ['body' => $body, 'title' => 'SocraNext preview', 'kind' => $kind === 'articles' ? 'archive' : $kind, 'origin' => $session['origin']];
        $originalFinder = view()->getFinder();
        view()->setFinder(\SocraNext\Statamic\Rendering\SiteViewFinder::forSite(\Statamic\Facades\Site::current()->handle()));
        try {
            $layout = \SocraNext\Statamic\Rendering\FrontendLayout::resolve();
            $bridge = view('socranext::public.preview-bridge', $data)->render();
            $html = \Statamic\View\View::make('socranext::public.preview-content', ['title' => $data['title'], 'socranext_preview_content' => new \Illuminate\Support\HtmlString($body.'<style id="socranext-preview-css"></style>'.$bridge)])
                ->layout($layout)->render();
        } finally {
            view()->setFinder($originalFinder);
        }
        return response($html, 200, ['Cache-Control' => 'private, no-store, max-age=0', 'X-Robots-Tag' => 'noindex, nofollow', 'Referrer-Policy' => 'no-referrer', 'Content-Security-Policy' => "frame-ancestors ".$session['origin'].'; base-uri \'none\'; object-src \'none\'']);
    }

    public function articles(Request $request)
    {
        abort_unless(app(\SocraNext\Statamic\Support\Readiness::class)->ready(), 404);
        return response(view('socranext::public.document', ['body' => $this->renderer->articles(), 'title' => 'Artikelen', 'kind' => 'archive', 'origin' => null])->render());
    }

    public function article(Request $request, string $slug)
    {
        abort_unless(app(\SocraNext\Statamic\Support\Readiness::class)->ready(), 404);
        $entry = collect($this->renderer->publishedEntries())->first(fn ($entry) => $entry->get('socranext_path', $entry->slug()) === $slug);
        abort_unless($entry, 404);
        return response(view('socranext::public.document', ['body' => $this->renderer->article($entry), 'title' => $entry->get('title'), 'kind' => 'article', 'origin' => null])->render());
    }
}
