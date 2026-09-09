<?php

namespace SocraNext\Statamic\Rendering;

use Illuminate\Support\Facades\Crypt;
use SocraNext\Statamic\Support\StateStore;

class PreviewSession
{
    public function __construct(private StateStore $store) {}

    public function issue(string $origin): array
    {
        $origin = rtrim($origin, '/');
        $parts = parse_url($origin);
        $allowed = config('socranext.preview_origins', [config('socranext.platform_url')]);
        abort_unless(is_array($parts) && isset($parts['host'], $parts['scheme'])
            && in_array($parts['scheme'], ['https','http'], true)
            && ! isset($parts['path']) && ! isset($parts['query']) && ! isset($parts['user']) && ! isset($parts['fragment'])
            && in_array($origin, array_map(fn ($v) => rtrim((string) $v, '/'), $allowed), true), 422, 'Preview origin is not allowed.');
        $ttl = max(60, min(900, (int) config('socranext.preview_ttl', 600)));
        $connection = $this->connectionDigest();
        abort_if($connection === null, 403, 'Connect SocraNext before opening a preview.');
        $token = Crypt::encryptString(json_encode(['origin' => $origin, 'site' => rtrim((string) config('socranext.site_url', config('app.url')), '/'), 'connection' => $connection, 'expires' => time() + $ttl, 'nonce' => bin2hex(random_bytes(16))]));
        return ['token' => $token, 'expires_in' => $ttl];
    }

    public function verify(string $token): array
    {
        try { $data = json_decode(Crypt::decryptString($token), true, 8, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { abort(403, 'Invalid preview token.'); }
        $allowed = array_map(fn ($v) => rtrim((string) $v, '/'), config('socranext.preview_origins', [config('socranext.platform_url')]));
        abort_unless(is_array($data) && is_int($data['expires'] ?? null) && $data['expires'] >= time()
            && $data['expires'] <= time() + 900 && in_array($data['origin'] ?? '', $allowed, true)
            && ($data['site'] ?? '') === rtrim((string) config('socranext.site_url', config('app.url')), '/')
            && is_string($data['connection'] ?? null) && ($digest = $this->connectionDigest()) !== null
            && hash_equals($digest, $data['connection']), 403, 'Expired or invalid preview token.');
        return $data;
    }

    private function connectionDigest(): ?string
    {
        return $this->store->transaction(function (array &$data) {
            return is_string($data['token_hash'] ?? null)
                ? hash('sha256', $data['token_hash']."\n".($data['connection_epoch'] ?? $data['connected_at'] ?? ''))
                : null;
        });
    }
}
