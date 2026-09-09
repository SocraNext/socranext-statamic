<?php

namespace SocraNext\Statamic\Content;

use SocraNext\Statamic\Support\StateStore;

/** Stable, never recycled bridge for legacy SocraNext numeric CMS columns. */
class IdentityMap
{
    public function __construct(private StateStore $store) {}

    public function id(string $kind, string $nativeId, string $site): int
    {
        return $this->store->transaction(function (array &$data) use ($kind, $nativeId, $site) {
            $key = hash('sha256', json_encode([$kind, $nativeId, $site], JSON_THROW_ON_ERROR));
            $id = max(1, (int) hexdec(substr($key, 0, 13)));
            $existing = $data['identities']['records'][$id] ?? null;
            if ($existing && [$existing['kind'], $existing['native_id'], $existing['site']] !== [$kind, $nativeId, $site]) {
                throw new \RuntimeException('Numeric identity collision; registry reconciliation is required.');
            }
            if ($existing) return $id;
            $data['identities']['keys'][$key] = $id;
            $data['identities']['records'][$id] = [
                'kind' => $kind, 'native_id' => $nativeId, 'site' => $site, 'deleted' => false,
            ];
            return $id;
        });
    }

    public function get(int|string $id): ?array
    {
        if (!ctype_digit((string) $id) || (int) $id < 1) return null;
        return $this->store->get('identities', [])['records'][(int) $id] ?? null;
    }

    public function tombstone(int $id): void
    {
        $this->store->transaction(function (array &$data) use ($id) {
            if (isset($data['identities']['records'][$id])) {
                $data['identities']['records'][$id]['deleted'] = true;
            }
        });
    }

    public function reactivate(int $id): void
    {
        $this->store->transaction(function (array &$data) use ($id) {
            if (isset($data['identities']['records'][$id])) $data['identities']['records'][$id]['deleted'] = false;
        });
    }
}
