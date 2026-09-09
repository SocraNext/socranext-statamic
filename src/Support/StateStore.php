<?php

namespace SocraNext\Statamic\Support;

use RuntimeException;

/** Durable site-local connector state. The separate lock survives atomic replacement. */
class StateStore
{
    private bool $locked = false;

    public function __construct(private ?string $path = null)
    {
        $this->path ??= config('socranext.state_path');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->transaction(fn (array &$data) => $data[$key] ?? $default);
    }

    public function put(string $key, mixed $value): void
    {
        $this->transaction(function (array &$data) use ($key, $value) { $data[$key] = $value; });
    }

    public function forget(string $key): void
    {
        $this->transaction(function (array &$data) use ($key) { unset($data[$key]); });
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->locked) throw new RuntimeException('Nested connector state transactions are not supported.');
        $directory = dirname($this->path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Connector storage is not writable.');
        }
        $lock = fopen($this->path.'.lock', 'c');
        if (!$lock) throw new RuntimeException('Connector lock cannot be opened.');
        @chmod($this->path.'.lock', 0600);
        $temporary = null;
        try {
            if (!flock($lock, LOCK_EX)) throw new RuntimeException('Connector state cannot be locked.');
            $this->locked = true;
            $data = is_file($this->path) ? json_decode(file_get_contents($this->path), true, 512, JSON_THROW_ON_ERROR) : [];
            if (!is_array($data)) throw new RuntimeException('Invalid connector state; restore the storage backup.');
            $original = $data;
            $result = $callback($data);
            if ($data !== $original) {
                $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $temporary = tempnam($directory, '.state-');
                if (!$temporary || file_put_contents($temporary, $json) !== strlen($json)) throw new RuntimeException('Connector state could not be saved.');
                chmod($temporary, 0600);
                if (!rename($temporary, $this->path)) throw new RuntimeException('Connector state could not be committed.');
                $temporary = null;
            }
            return $result;
        } finally {
            if ($temporary && is_file($temporary)) @unlink($temporary);
            $this->locked = false;
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
