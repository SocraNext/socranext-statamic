<?php

namespace SocraNext\Statamic\Rendering;

class CodeSignature
{
    public function host(): string
    {
        $url = trim((string) config('socranext.site_url', config('app.url')));
        if (! str_contains($url, '://')) $url = 'https://'.$url;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    public function configured(): bool
    {
        return function_exists('sodium_crypto_sign_verify_detached') && strlen($this->key()) === 32 && $this->host() !== '';
    }

    private function key(): string
    {
        $raw = trim((string) config('socranext.code_signing_public_key', ''));
        if (str_starts_with($raw, '-----BEGIN')) $raw = preg_replace('/-----[^-]+-----|\s/', '', $raw);
        $decoded = base64_decode($raw, true);
        if ($decoded === false) return '';
        // Ed25519 SubjectPublicKeyInfo prefix, as exported by Node's crypto module.
        $prefix = hex2bin('302a300506032b6570032100');
        return strlen($decoded) === 44 && str_starts_with($decoded, $prefix) ? substr($decoded, 12) : $decoded;
    }

    public function valid(string $slot, string $code, string $signature): bool
    {
        if ($code === '') return true;
        if (! in_array($slot, ['faq','blog','articles'], true) || ! $this->configured()) return false;
        $signature = base64_decode($signature, true);
        if ($signature === false || strlen($signature) !== 64) return false;
        return sodium_crypto_sign_verify_detached($signature, "socranext.code.v1\n".$this->host()."\n".$slot."\n".$code, $this->key());
    }
}
