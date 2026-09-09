<?php

namespace SocraNext\Statamic\Rendering;

use SocraNext\Statamic\Content\ContentRepository;
use SocraNext\Statamic\Support\StateStore;
use Statamic\Facades\{Asset, Collection, Entry, Site, Term};

class Renderer
{
    public function __construct(private StateStore $store, private ContentRepository $content, public SafeMarkup $safe, private CodeSignature $signatures) {}

    public function style(string $slot, ?string $language = null): array
    {
        $data = $this->store->get('styles', [])[$slot] ?? [];
        $language ??= Site::current()->lang();
        // Matches shared/stylerTexts.js normalizeStylerLang: the platform sends primary language keys.
        $language = explode('-', str_replace('_', '-', strtolower(trim($language))))[0];
        foreach (['title_text','button_text','disclaimer_text','layout_settings'] as $key) {
            $map = $data[$key.'_i18n'] ?? [];
            if (array_key_exists($language, $map)) {
                $data[$key] = $key === 'layout_settings'
                    ? array_replace(is_array($data[$key] ?? null) ? $data[$key] : [], $map[$language])
                    : $map[$language];
            }
        }
        return $data;
    }

    public function assets(string $slot, array $style = []): string
    {
        $css = file_get_contents(__DIR__.'/../../resources/views/public/base.css');
        $extra = $this->safe->css((string) ($style['custom_css'] ?? ''));
        return '<style data-socranext-style="'.$slot.'">'.$css."\n".$extra.'</style>';
    }

    public function scripts(string $slot, array $style): string
    {
        $code = (string) ($style['custom_js'] ?? '');
        if ($code !== '' && $this->signatures->valid($slot, $code, (string) ($style['custom_js_sig'] ?? ''))) {
            return '<script data-socranext-signed="'.$slot.'">'.str_ireplace('</script', '<\\/script', $code).'</script>';
        }
        return '';
    }

    public function faq(int|string $id, bool $preview = false): string
    {
        if (! app(\SocraNext\Statamic\Support\Readiness::class)->ready() && ! $preview) return '';
        $resource = is_numeric($id) ? null : (Entry::find($id) ?? Term::find($id)?->in(Site::current()->handle()));
        if (is_numeric($id) && ($record = $this->content->identities->get($id))) {
            if ($record['deleted'] ?? false) return '';
            $resource = $record['kind'] === 'entry' ? Entry::find($record['native_id']) : Term::find($record['native_id'])?->in($record['site']);
            if (! $resource) return '';
        }
        if ($resource) {
            if (($resource->private() || (method_exists($resource, 'status') && $resource->status() !== 'published')) && ! $preview) return '';
            $id = $this->content->identity($resource);
        } elseif (! $preview) return '';
        $faq = $this->store->get('faqs', [])[$id] ?? [];
        if ((! ($faq['enabled'] ?? false) || empty($faq['questions'])) && ! $preview) return '';
        $style = $this->style('faq', $resource ? Site::get($resource->locale())->lang() : null);
        $questions = $faq['questions'] ?? [];
        if ($preview && ! $questions) $questions = [['question' => 'Hoe werkt dit?', 'answer' => '<p>Dit is voorbeeldinhoud voor de FAQ-opmaak.</p>']];
        $questions = array_map(fn ($q) => ['question' => strip_tags((string) ($q['question'] ?? '')), 'answer' => $this->safe->html((string) ($q['answer'] ?? ''))], $questions);
        $schema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q['answer']]], $questions)];
        return view('socranext::public.faq', [
            'id' => $id, 'questions' => $questions, 'schema' => $this->safe->json($schema),
            'title' => $style['title_text'] ?? 'Veelgestelde vragen', 'disclaimer' => $this->safe->html((string) ($style['disclaimer_text'] ?? '')),
            'extra' => $this->safe->html((string) ($style['custom_html'] ?? '')).$this->safe->html((string) ($faq['custom_html'] ?? '')),
            'assets' => $this->assets('faq', $style).'<style>'.$this->safe->css((string) ($faq['custom_css'] ?? '')).'</style>',
            'revision' => substr(hash('sha256', json_encode([$faq, $style])), 0, 12),
        ])->render().$this->scripts('faq', $style);
    }

    public function publishedEntries(): array
    {
        if (! Collection::find($this->content->managedCollection())) return [];
        return Entry::query()->where('collection', $this->content->managedCollection())->where('site', Site::current()->handle())
            ->whereStatus('published')->get()->filter(fn ($entry) => $entry->get('socranext_owned') === true && ! $entry->private())->values()->all();
    }

    public function article($entry = null, bool $preview = false): string
    {
        if (! $entry && ! $preview) return '';
        if ($entry && ! $preview && ($entry->status() !== 'published' || $entry->private() || $entry->get('socranext_owned') !== true)) return '';
        $style = $this->style('blog', $entry ? Site::get($entry->locale())->lang() : null);
        $settings = is_array($style['layout_settings'] ?? null) ? $style['layout_settings'] : [];
        $body = $this->safe->html((string) ($entry?->get('content') ?? '<p>Dit is voorbeeldinhoud voor de artikelopmaak.</p><h2>Een duidelijke tussenkop</h2><p>Hier ziet u tekst en <a href="#">een link</a>.</p>'));
        $toc = [];
        $body = preg_replace_callback('/<h([23])(?:\s[^>]*)?>(.*?)<\/h\1>/is', function ($match) use (&$toc) {
            $id = 'sn-heading-'.(count($toc) + 1);
            $toc[] = ['id' => $id, 'level' => $match[1], 'text' => strip_tags($match[2])];
            return '<h'.$match[1].' id="'.$id.'">'.$match[2].'</h'.$match[1].'>';
        }, $body);
        $related = array_slice(array_filter($this->publishedEntries(), fn ($other) => ! $entry || $other->id() !== $entry->id()), 0, 3);
        return view('socranext::public.article', [
            'title' => $entry?->get('title') ?? 'Voorbeeldartikel', 'body' => $body, 'entry' => $entry,
            'style' => $style, 'settings' => $settings, 'v2' => ($style['layout'] ?? '') === 'v2', 'toc' => $toc,
            'safe' => $this->safe, 'related' => $related, 'takeaways' => (array) ($entry?->get('socranext_key_takeaways') ?? []),
            'hero' => $entry ? $this->hero($entry) : '',
            'assets' => $this->assets('blog', $style), 'extra' => $this->safe->html((string) ($style['custom_html'] ?? '')),
            'faq' => $entry ? $this->faq($entry->id()) : '', 'archiveUrl' => $this->archiveUrl(),
            'archiveTitle' => $this->style('articles')['title_text'] ?? 'Artikelen',
        ])->render().$this->scripts('blog', $style);
    }

    public function articles(bool $preview = false): string
    {
        $style = $this->style('articles');
        $entries = $this->publishedEntries();
        $cards = array_map(fn ($entry) => ['title' => $entry->get('title'), 'url' => $entry->absoluteUrl(), 'excerpt' => $entry->get('socranext_meta_description', ''), 'image' => $this->hero($entry)], $entries);
        if ($preview && ! $cards) $cards = [['title' => 'Voorbeeldartikel', 'url' => '#', 'excerpt' => 'Voorbeeldinhoud voor de artikelcollectie.', 'image' => '']];
        return view('socranext::public.articles', ['cards' => $cards, 'title' => $style['title_text'] ?? 'Artikelen', 'button' => $style['button_text'] ?? 'Lees artikel', 'safe' => $this->safe, 'extra' => $this->safe->html((string) ($style['custom_html'] ?? '')), 'assets' => $this->assets('articles', $style)])->render().$this->scripts('articles', $style);
    }

    private function hero($entry): string
    {
        $id = $entry->get('featured_image');
        if (is_array($id)) $id = $id[0] ?? null;
        return is_string($id) ? $this->safe->url((string) Asset::find($id)?->absoluteUrl()) : '';
    }

    public function archiveUrl(): string
    {
        $slug = $this->store->get('articles_slugs', [])[Site::current()->handle()] ?? config('socranext.content.articles_slug', 'artikelen-sn');
        return rtrim(Site::current()->absoluteUrl(), '/').'/'.trim($slug, '/');
    }

    public function metadata($resource): string
    {
        if (! $resource || $resource->private() || (! $this->content->isTerm($resource) && $resource->status() !== 'published')) return '';
        $override = $this->store->get('metadata', [])[$this->content->identity($resource)] ?? null;
        if ($override === null && $resource->get('socranext_owned') !== true) return '';
        $override ??= [];
        $title = (string) ($override['title_tag'] ?? ($resource->get('socranext_title_tag') ?: $resource->get('title')));
        $description = (string) ($override['meta_description'] ?? $resource->get('socranext_meta_description', ''));
        $html = '<title>'.e($title).'</title><meta name="description" content="'.e($description).'"><link rel="canonical" href="'.e($resource->absoluteUrl()).'">';
        $schema = $override['json_ld'] ?? $resource->get('socranext_json_ld', []);
        if (is_string($schema)) { try { $schema = json_decode($schema, true, 64, JSON_THROW_ON_ERROR); } catch (\Throwable) { $schema = []; } }
        if (is_array($schema) && $schema !== []) $html .= '<script type="application/ld+json">'.$this->safe->json($schema).'</script>';
        return $html;
    }
}
