<?php

namespace SocraNext\Statamic\Support;

class Connection
{
    public function __construct(private StateStore $store) {}

    public function begin(): string
    {
        $state = bin2hex(random_bytes(32));
        $this->store->put('connect_state', ['hash' => hash('sha256', $state), 'expires' => time() + (int) config('socranext.connect_ttl', 300)]);
        return $state;
    }

    public function receive(string $state, string $token): bool
    {
        if (strlen($state) !== 64 || strlen($token) < 32 || strlen($token) > 512) return false;
        return $this->store->transaction(function (array &$data) use ($state, $token) {
            $pending = $data['connect_state'] ?? null;
            if (!$pending || !hash_equals($pending['hash'], hash('sha256', $state)) || $pending['expires'] <= time()) return false;
            // Token hash and state consumption commit together; replay cannot rotate the credential.
            $data['token_hash'] = hash('sha256', $token);
            $data['connected_at'] = gmdate(DATE_ATOM);
            $data['connection_epoch'] = bin2hex(random_bytes(16));
            unset($data['connect_state']);
            return true;
        });
    }

    public function accepts(string $token): bool
    {
        $hash = $this->store->get('token_hash');
        return $token !== '' && is_string($hash) && hash_equals($hash, hash('sha256', $token));
    }

    public function connected(): bool { return is_string($this->store->get('token_hash')); }

    public function pending(): bool { return $this->store->get('connect_state') !== null; }

    public function disconnect(): void
    {
        $this->store->transaction(function (array &$data) {
            unset($data['token_hash'], $data['connect_state'], $data['connected_at'], $data['connection_epoch']);
        });
    }
}
