<?php

namespace SocraNext\Statamic\Http\Controllers;

use Illuminate\Http\Request;
use SocraNext\Statamic\Content\ContentRepository;
use SocraNext\Statamic\Rendering\{SafeMarkup, CodeSignature, PreviewSession};
use SocraNext\Statamic\ServiceProvider;
use SocraNext\Statamic\Support\StateStore;

class PresentationController
{
    public function __construct(private StateStore $store, private ContentRepository $content, private SafeMarkup $safe, private CodeSignature $signatures, private PreviewSession $previews) {}

    public function getFaq(Request $request, int|string $id, string $type = 'pages', ?string $cpt = null)
    {
        $id = $this->content->identity($this->content->resolve($type, $id, $cpt));
        $faq = $this->store->get('faqs', [])[$id] ?? ['questions' => [], 'enabled' => false, 'custom_html' => '', 'custom_css' => ''];
        return response()->json($faq + ['success' => true, 'page_id' => $id, 'custom_js' => '']);
    }

    public function storeFaq(Request $request, string $type = 'pages', ?string $cpt = null)
    {
        $data = $request->validate(['page_id' => 'required|integer|min:1', 'questions' => 'present|array|max:100', 'questions.*.question' => 'required|string|max:2000', 'questions.*.answer' => 'required|string|max:50000', 'custom_html' => 'nullable|string|max:200000', 'custom_css' => 'nullable|string|max:200000', 'custom_js' => 'nullable|string|max:200000']);
        $resource = $this->content->resolve($type, $data['page_id'], $cpt);
        $id = $this->content->identity($resource);
        $faq = ['questions' => array_map(fn ($q) => ['question' => strip_tags($q['question']), 'answer' => $this->safe->html($q['answer'])], $data['questions']), 'custom_html' => $this->safe->html($data['custom_html'] ?? ''), 'custom_css' => $this->safe->css($data['custom_css'] ?? ''), 'updated_at' => now()->toIso8601String()];
        // Per-resource payloads have no platform signature. Never turn these into executable code.
        abort_if(($data['custom_js'] ?? '') !== '', 422, 'Per-resource JavaScript is unsupported; use signed site-wide styles.');
        $this->store->transaction(function (array &$state) use ($id, $faq) { $state['faqs'][$id] = $faq + ['enabled' => $state['faqs'][$id]['enabled'] ?? false]; });
        $this->invalidate();
        return response()->json(['success' => true, 'page_id' => $id, 'questions' => $faq['questions']]);
    }

    public function toggleFaq(Request $request, string $type = 'pages', ?string $cpt = null)
    {
        $data = $request->validate(['page_id' => 'required|integer|min:1', 'enabled' => 'required|boolean']);
        $id = $this->content->identity($this->content->resolve($type, $data['page_id'], $cpt));
        abort_if($data['enabled'] && ! app(\SocraNext\Statamic\Support\Readiness::class)->ready(), 409, 'The website developer must verify the FAQ template before activation.');
        $this->store->transaction(function (array &$state) use ($id, $data) { $state['faqs'][$id] = array_replace($state['faqs'][$id] ?? ['questions' => [], 'custom_html' => '', 'custom_css' => ''], ['enabled' => (bool) $data['enabled']]); });
        $this->invalidate();
        return response()->json(['success' => true, 'page_id' => $id, 'enabled' => (bool) $data['enabled']]);
    }

    public function storeStyle(Request $request, string $slot)
    {
        abort_unless(in_array($slot, ['faq','blog','articles'], true), 404);
        $data = $request->validate(['custom_html' => 'nullable|string|max:500000', 'custom_css' => 'nullable|string|max:500000', 'custom_js' => 'nullable|string|max:500000', 'custom_js_sig' => 'nullable|string|max:200', 'title_text' => 'nullable|string|max:2000', 'button_text' => 'nullable|string|max:500', 'disclaimer_text' => 'nullable|string|max:10000', 'title_text_i18n' => 'sometimes|array|max:100', 'button_text_i18n' => 'sometimes|array|max:100', 'disclaimer_text_i18n' => 'sometimes|array|max:100', 'layout' => 'sometimes|in:classic,v2', 'layout_settings' => 'sometimes|array|max:40', 'layout_settings_i18n' => 'sometimes|array|max:100']);
        $layoutRules = ['layout_settings_i18n.*' => 'array|max:40'];
        foreach (['layout_settings.', 'layout_settings_i18n.*.'] as $prefix) {
            foreach (['tocTitle','takeawaysTitle','relatedTitle','homeLabel','ctaTitle','ctaText','ctaButtonText','authorName','authorRole','authorBio','ctaButtonUrl','authorUrl','authorImage'] as $key) $layoutRules[$prefix.$key] = 'sometimes|nullable|string|max:10000';
            foreach (['showBreadcrumbs','showDate','showHero','showToc','showProgress','showRelated','showTakeaways','showCta','showAuthor'] as $key) $layoutRules[$prefix.$key] = 'sometimes|boolean';
            $layoutRules[$prefix.'ctaPosition'] = 'sometimes|in:both,end';
        }
        \Illuminate\Support\Facades\Validator::make($data, $layoutRules)->validate();
        $data['custom_html'] = $this->safe->html($data['custom_html'] ?? '');
        $data['custom_css'] = $this->safe->css($data['custom_css'] ?? '');
        $data['custom_js'] ??= '';
        $data['custom_js_sig'] ??= '';
        abort_unless($this->signatures->valid($slot, $data['custom_js'], $data['custom_js_sig']), 422, 'Custom JavaScript requires a valid SocraNext signature for this site and slot.');
        foreach (['title_text_i18n','button_text_i18n','disclaimer_text_i18n'] as $key) {
            foreach ($data[$key] ?? [] as $language => $value) abort_unless(is_string($value) && strlen($value) <= 10000, 422, 'Invalid translated text.');
        }
        $this->store->transaction(function (array &$state) use ($slot, $data) { $state['styles'][$slot] = array_replace($state['styles'][$slot] ?? [], $data); });
        $this->invalidate();
        return response()->json(['success' => true]);
    }

    public function getStyle(Request $request, string $slot)
    {
        abort_unless(in_array($slot, ['faq','blog','articles'], true), 404);
        return response()->json($this->store->get('styles', [])[$slot] ?? ['custom_html' => '', 'custom_css' => '', 'custom_js' => '', 'custom_js_sig' => '']);
    }

    public function storeLlms(Request $request)
    {
        abort_if(is_file(public_path('llms.txt')) || is_file(public_path('llms-full.txt')), 409, 'Existing public llms files take precedence. Remove or relocate them before enabling connector-managed routes.');
        $data = $request->validate(['llmsTxt' => 'present|string|max:2000000', 'llmsFullTxt' => 'nullable|string|max:5000000']);
        $this->store->put('llms', ['llmsTxt' => $data['llmsTxt'], 'llmsFullTxt' => $data['llmsFullTxt'] ?? '']);
        $this->invalidate();
        return response()->json(['success' => true]);
    }

    public function previewToken(Request $request)
    {
        $data = $request->validate(['origin' => 'required|string|max:300']);
        $session = $this->previews->issue($data['origin']);
        $session['urls'] = [];
        foreach (['faq','article','articles'] as $kind) $session['urls'][$kind] = url('/socranext/preview/'.$kind).'?token='.rawurlencode($session['token']);
        return response()->json($session + ['success' => true, 'version' => ServiceProvider::VERSION]);
    }

    public function renderMode(Request $request)
    {
        if ($request->isMethod('GET')) return response()->json(['mode' => 'shortcode', 'supported_modes' => ['shortcode']]);
        $data = $request->validate(['mode' => 'required|in:auto,shortcode']);
        // Statamic requires an explicit template tag in both modes. No unsafe HTML response injection.
        abort_unless($data['mode'] === 'shortcode', 422, 'Statamic uses explicit template placement; auto injection is unsupported.');
        $this->store->put('render_mode', 'shortcode');
        return response()->json(['success' => true, 'mode' => 'shortcode']);
    }

    private function invalidate(): void
    {
        // These records are not Entry saves; clear native static output explicitly.
        if (config('statamic.static_caching.strategy')) \Statamic\Facades\StaticCache::flush();
    }

    public function cptStoreFaq(Request $request, string $cpt) { return $this->storeFaq($request, 'custom', $cpt); }
    public function cptToggleFaq(Request $request, string $cpt) { return $this->toggleFaq($request, 'custom', $cpt); }
    public function cptGetFaq(Request $request, string $cpt, int|string $id) { return $this->getFaq($request, $id, 'custom', $cpt); }
}
