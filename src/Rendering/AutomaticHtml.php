<?php

namespace SocraNext\Statamic\Rendering;

/** Locate HTML spans without serializing customer markup, scripts or attributes. */
class AutomaticHtml
{
    public function render(string $html, string $faq, string $faqId, string $metadata, array $metadataFields = ['title', 'description', 'canonical', 'schema']): string
    {
        $tokens = $this->tokens($html);
        if ($tokens === null || !($document = $this->document($tokens))) return $html;
        $changes = [];
        if ($faq !== '' && !$this->hasId($tokens, $faqId, $document['body'])) {
            $changes[] = [$document['main'] ?? $document['body'][1], 0, $faq];
        }
        if ($metadata !== '' && $document['head'] !== null) {
            $changes = [...$changes, ...$this->metadataChanges($html, $tokens, $document['head'], $metadata, $metadataFields)];
        }
        // All offsets refer to the original document. Descending edits preserve them.
        usort($changes, fn ($a, $b) => $b[0] <=> $a[0]);
        foreach ($changes as [$offset, $length, $replacement]) {
            $html = substr_replace($html, $replacement, $offset, $length);
        }
        return $html;
    }

    private function metadataChanges(string $html, array $tokens, array $head, string $metadata, array $fields): array
    {
        $desired = $this->tokens($metadata);
        if ($desired === null) return [];
        $changes = [];
        $append = '';
        foreach (['title', 'description', 'canonical'] as $kind) {
            if (!in_array($kind, $fields, true)) continue;
            $replacement = null;
            foreach ($desired as $token) {
                if ($this->metadataKind($token) === $kind) {
                    $replacement = substr($metadata, $token['start'], ($token['elementEnd'] ?? $token['end']) - $token['start']);
                    break;
                }
            }
            if ($replacement === null) continue;
            $found = false;
            foreach ($tokens as $token) {
                if (!$this->inside($token, $head) || $this->metadataKind($token) !== $kind) continue;
                $changes[] = [$token['start'], ($token['elementEnd'] ?? $token['end']) - $token['start'], $found ? '' : $replacement];
                $found = true;
            }
            if (!$found) $append .= $replacement;
        }
        foreach ($desired as $token) {
            if (!in_array('schema', $fields, true)) break;
            if (($schema = $this->schema($metadata, $token)) === null) continue;
            $alreadyPresent = false;
            foreach ($tokens as $existing) {
                if ($this->inside($existing, $head) && $this->schema($html, $existing) == $schema) {
                    $alreadyPresent = true;
                    break;
                }
            }
            if (!$alreadyPresent) {
                $append .= substr($metadata, $token['start'], $token['elementEnd'] - $token['start']);
            }
        }
        if ($append !== '') $changes[] = [$head[1], 0, $append];
        return $changes;
    }

    private function metadataKind(array $token): ?string
    {
        if ($token['closing'] || $token['inert']) return null;
        if ($token['name'] === 'title') return 'title';
        if ($token['name'] === 'meta' && strtolower($token['attributes']['name'] ?? '') === 'description') return 'description';
        if ($token['name'] === 'link' && in_array('canonical', preg_split('/\s+/', strtolower(trim($token['attributes']['rel'] ?? ''))), true)) return 'canonical';
        return null;
    }

    private function schema(string $html, array $token): ?array
    {
        if ($token['inert'] || $token['closing'] || $token['name'] !== 'script'
            || strtolower(trim($token['attributes']['type'] ?? '')) !== 'application/ld+json'
            || !isset($token['contentEnd'])) return null;
        try {
            $value = json_decode(substr($html, $token['end'], $token['contentEnd'] - $token['end']), true, 64, JSON_THROW_ON_ERROR);
            return is_array($value) && $value !== [] ? $value : null;
        } catch (\JsonException) {
            return null;
        }
    }

    private function hasId(array $tokens, string $id, array $body): bool
    {
        foreach ($tokens as $token) {
            if (!$token['closing'] && $this->inside($token, $body) && ($token['attributes']['id'] ?? '') === $id) return true;
        }
        return false;
    }

    private function inside(array $token, array $range): bool
    {
        return !$token['inert'] && $token['start'] >= $range[0] && ($token['elementEnd'] ?? $token['end']) <= $range[1];
    }

    private function document(array $tokens): ?array
    {
        $structural = ['html' => [[], []], 'head' => [[], []], 'body' => [[], []]];
        foreach ($tokens as $token) {
            if (!$token['inert'] && isset($structural[$token['name']])) $structural[$token['name']][$token['closing'] ? 1 : 0][] = $token;
        }
        foreach (['html', 'body'] as $name) {
            if (count($structural[$name][0]) !== 1 || count($structural[$name][1]) !== 1) return null;
        }
        [$htmlOpen, $htmlClose] = [$structural['html'][0][0], $structural['html'][1][0]];
        [$bodyOpen, $bodyClose] = [$structural['body'][0][0], $structural['body'][1][0]];
        if (!($htmlOpen['end'] <= $bodyOpen['start'] && $bodyOpen['end'] <= $bodyClose['start'] && $bodyClose['end'] <= $htmlClose['start'])) return null;
        $body = [$bodyOpen['end'], $bodyClose['start']];
        $head = null;
        if ($structural['head'] !== [[], []]) {
            if (count($structural['head'][0]) !== 1 || count($structural['head'][1]) !== 1) return null;
            [$open, $close] = [$structural['head'][0][0], $structural['head'][1][0]];
            if (!($htmlOpen['end'] <= $open['start'] && $open['end'] <= $close['start'] && $close['end'] <= $bodyOpen['start'])) return null;
            $head = [$open['end'], $close['start']];
        }
        $main = null;
        $depth = 0;
        foreach ($tokens as $token) {
            if (!$this->inside($token, $body) || $token['name'] !== 'main') continue;
            if (!$token['closing']) $depth++;
            elseif ($depth === 0) return null;
            elseif (--$depth === 0 && $main === null) $main = $token['start'];
        }
        if ($depth !== 0) return null;
        return compact('head', 'body', 'main');
    }

    /** Raw-text and inert elements never contribute document boundaries or dedupe markers. */
    private function tokens(string $html): ?array
    {
        $length = strlen($html);
        if ($length > 20 * 1024 * 1024) return null;
        $tokens = [];
        $offset = 0;
        $inert = [];
        while (($start = strpos($html, '<', $offset)) !== false) {
            if (substr($html, $start, 4) === '<!--') {
                if (($end = strpos($html, '-->', $start + 4)) === false) return null;
                $offset = $end + 3;
                continue;
            }
            if (substr($html, $start, 9) === '<![CDATA[') {
                if (($end = strpos($html, ']]>', $start + 9)) === false) return null;
                $offset = $end + 3;
                continue;
            }
            if (!preg_match('/\G<(\/?)([a-z][a-z0-9:-]*)(?=[\s\/>])/i', $html, $match, 0, $start)) {
                if (in_array(substr($html, $start, 2), ['<!', '<?'], true)) {
                    if (($end = $this->tagEnd($html, $start + 2)) === null) return null;
                    $offset = $end;
                } else $offset = $start + 1;
                continue;
            }
            $name = strtolower($match[2]);
            $closing = $match[1] === '/';
            $attributesStart = $start + strlen($match[0]);
            if (($end = $this->tagEnd($html, $attributesStart)) === null) return null;
            $token = ['start' => $start, 'end' => $end, 'name' => $name, 'closing' => $closing, 'inert' => $inert !== [],
                'attributes' => $closing ? [] : $this->attributes(substr($html, $attributesStart, $end - $attributesStart - 1))];
            $offset = $end;
            if (!$closing && in_array($name, ['script', 'style', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes', 'noscript'], true)) {
                if (!preg_match('~</'.preg_quote($name, '~').'\s*>~i', $html, $close, PREG_OFFSET_CAPTURE, $end)) return null;
                // Legacy double-escaped script syntax has different HTML end-tag rules.
                // Leave that uncommon document untouched rather than guess its boundary.
                if ($name === 'script' && preg_match('~<!--.*<script(?=[\s/>])~is', substr($html, $end, $close[0][1] - $end))) return null;
                $token['contentEnd'] = $close[0][1];
                $token['elementEnd'] = $offset = $close[0][1] + strlen($close[0][0]);
            } elseif (!$closing && $name === 'plaintext') {
                return null;
            }
            $tokens[] = $token;
            // Bound temporary arrays as well as bytes on unusually large customer pages.
            if (count($tokens) > 10000) return null;
            if (in_array($name, ['template', 'svg', 'math'], true)) {
                if ($closing) {
                    if (array_pop($inert) !== $name) return null;
                } elseif (!($name !== 'template' && preg_match('~/\s*>$~', substr($html, $start, $end - $start)))) $inert[] = $name;
            }
        }
        return $inert === [] ? $tokens : null;
    }

    private function tagEnd(string $html, int $offset): ?int
    {
        $quote = null;
        $length = strlen($html);
        for (; $offset < $length; $offset++) {
            $character = $html[$offset];
            if ($quote !== null) {
                if ($character === $quote) $quote = null;
            } elseif ($character === '"' || $character === "'") $quote = $character;
            elseif ($character === '>') return $offset + 1;
            elseif ($character === '<') return null;
        }
        return null;
    }

    private function attributes(string $html): array
    {
        preg_match_all('/([^\s=\/>]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?/', $html, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        $attributes = [];
        foreach ($matches as $match) {
            $name = strtolower($match[1]);
            if (!array_key_exists($name, $attributes)) $attributes[$name] = html_entity_decode($match[2] ?? $match[3] ?? $match[4] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $attributes;
    }
}
