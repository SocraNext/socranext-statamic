<?php

namespace SocraNext\Statamic\Tests;

use PHPUnit\Framework\TestCase;
use SocraNext\Statamic\Rendering\AutomaticHtml;

class AutomaticHtmlTest extends TestCase
{
    public function test_fake_document_boundaries_and_ids_are_ignored_without_rewriting_javascript_or_attributes(): void
    {
        $fake = '<div id="socranext-frontend-block-42"></div></main></body>';
        $before = '<!doctype html><HTML><HEAD><script>const template = '.json_encode($fake).'; const head="</head>";</script><style>.a:after{content:"</head></main>"}</style></HEAD><BODY>'
            .'<!-- '.$fake.' --><main x-data="{example: \''.$fake.'\'}"><p>Real content</p>'
            .'<textarea>'.$fake.'</textarea><template>'.$fake.'</template><svg><text>'.$fake.'</text></svg>';
        $after = '</main><footer>Customer footer</footer></BODY></HTML>';
        $inserted = '<div id="socranext-frontend-block-42">Real FAQ</div>';
        $this->assertSame($before.$inserted.$after, (new AutomaticHtml)->render($before.$after, $inserted, 'socranext-frontend-block-42', ''));
    }

    public function test_body_fallback_and_actual_single_quoted_entity_encoded_id_dedupe(): void
    {
        $helper = new AutomaticHtml;
        $html = '<html><head></head><body><svg/><article>No main</article></body></html>';
        $faq = '<div id="socranext-frontend-block-42">FAQ</div>';
        $this->assertSame(str_replace('</body>', $faq.'</body>', $html), $helper->render($html, $faq, 'socranext-frontend-block-42', ''));
        $existing = '<html><head></head><body><div ID=\'socranext-frontend-block-4&#50;\'>FAQ</div></body></html>';
        $this->assertSame($existing, $helper->render($existing, $faq, 'socranext-frontend-block-42', ''));
    }

    public function test_fragments_or_incomplete_documents_are_not_touched(): void
    {
        foreach (['<main>Fragment</main>', '<html><body>Missing close', '<html><body><main>Unclosed main</body></html>', '<html><body><!-- unclosed</body></html>', '<html><body><script>unclosed</body></html>'] as $html) {
            $this->assertSame($html, (new AutomaticHtml)->render($html, '<div>FAQ</div>', 'faq', '<title>New</title>'));
        }
    }

    public function test_legacy_double_escaped_script_documents_fail_open_without_guessing_boundaries(): void
    {
        $html = '<html><head><script><!--<script></script></head><body><main>Inside script</main></body>--></script></head><body>Real</body></html>';
        $this->assertSame($html, (new AutomaticHtml)->render($html, '<div>FAQ</div>', 'faq', ''));
    }

    public function test_extremely_dense_documents_are_returned_unchanged_with_bounded_scanning(): void
    {
        $html = '<html><head></head><body><main>'.str_repeat('<span>Original</span>', 6000).'</main></body></html>';
        $this->assertSame($html, (new AutomaticHtml)->render($html, '<div>FAQ</div>', 'faq', ''));
    }

    public function test_metadata_deduplicates_only_its_own_json_and_keeps_other_schema_and_body_exactly(): void
    {
        $other = '<script type="application/ld+json">{"@type":"Organization","name":"Customer"}</script>';
        $own = '<script type="application/ld+json">{ "headline": "Own", "@type": "Article" }</script>';
        $head = '<head><title>Old</title><title>Duplicate</title><meta name=description content=Old><meta name=description content=Duplicate><link rel=canonical href=old><link rel=canonical href=duplicate>'.$other.$own.'</head>';
        $body = '<body><main data-html="<title>Literal</title>">Original</main></body>';
        $desired = '<title>New</title><meta name="description" content="New"><link rel="canonical" href="https://example.com/new"><script type="application/ld+json">{"@type":"Article","headline":"Own"}</script>';
        $result = (new AutomaticHtml)->render('<html>'.$head.$body.'</html>', '', 'unused', $desired);
        $this->assertSame('<html><head><title>New</title><meta name="description" content="New"><link rel="canonical" href="https://example.com/new">'.$other.$own.'</head>'.$body.'</html>', $result);
    }
}
