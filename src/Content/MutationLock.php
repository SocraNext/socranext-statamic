<?php

namespace SocraNext\Statamic\Content;

/** Serialize connector writes, separately from the short state transactions. */
class MutationLock
{
    public function run(callable $callback): mixed
    {
        $path = config('socranext.state_path').'.content.lock';
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        $handle = fopen($path, 'c');
        if (!$handle) throw new \RuntimeException('Content lock cannot be opened.');
        @chmod($path, 0600);
        try {
            if (!flock($handle, LOCK_EX)) throw new \RuntimeException('Content cannot be locked.');
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
