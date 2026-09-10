<?php

namespace SocraNext\Statamic\Rendering;

/** HTML is data: never evaluate incoming Blade, Antlers, event handlers or scripts. */
class SafeMarkup
{
    public function html(string $html): string
    {
        if ($html === '') return '';
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $document->getElementsByTagName('body')->item(0);
        if (! $body) return '';
        $allowed = ['div','span','p','br','hr','strong','b','em','i','u','s','small','sup','sub','h1','h2','h3','h4','h5','h6','ul','ol','li','a','blockquote','pre','code','table','thead','tbody','tfoot','tr','th','td','figure','figcaption','img','section','aside'];
        $drop = ['script','style','iframe','object','embed','svg','math','template','form','input','button','textarea','select','link','meta','base'];
        $clean = function (\DOMNode $parent) use (&$clean, $allowed, $drop): void {
            foreach (iterator_to_array($parent->childNodes) as $node) {
                if ($node instanceof \DOMComment || $node instanceof \DOMProcessingInstruction) { $parent->removeChild($node); continue; }
                if (! $node instanceof \DOMElement) continue;
                $tag = strtolower($node->tagName);
                if (in_array($tag, $drop, true)) { $parent->removeChild($node); continue; }
                $clean($node);
                if (! in_array($tag, $allowed, true)) {
                    while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                    $parent->removeChild($node);
                    continue;
                }
                foreach (iterator_to_array($node->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    $valid = in_array($name, ['class','id','title','lang','dir'], true)
                        || ($tag === 'a' && in_array($name, ['href','rel'], true))
                        || ($tag === 'img' && in_array($name, ['src','alt','width','height','loading'], true))
                        || (in_array($tag, ['th','td'], true) && in_array($name, ['colspan','rowspan','scope'], true));
                    if (! $valid || (in_array($name, ['href','src'], true) && $this->url($attribute->value) === '')) $node->removeAttribute($attribute->name);
                }
                if ($tag === 'a') $node->setAttribute('rel', 'noopener noreferrer');
            }
        };
        $clean($body);
        $wrapper = $body->firstChild;
        if (! $wrapper) return '';
        $result = '';
        foreach ($wrapper->childNodes as $child) $result .= $document->saveHTML($child);
        return $result;
    }

    public function url(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || preg_match('/[\x00-\x20\\\\]/', $url)) return '';
        if (str_starts_with($url, '#') || (str_starts_with($url, '/') && ! str_starts_with($url, '//'))) return $url;
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http','https','mailto','tel'], true) ? $url : '';
    }

    public function css(string $css): string
    {
        // A less-than sign can start HTML breakout; greater-than signs are valid CSS combinators.
        if (preg_match('/<|expression\s*\(|javascript\s*:|vbscript\s*:|-moz-binding|behavior\s*:/i', $css)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['custom_css' => 'Unsafe CSS was rejected.']);
        }
        return $css;
    }

    public function json(mixed $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
