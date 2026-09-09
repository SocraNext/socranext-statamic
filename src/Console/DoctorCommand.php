<?php

namespace SocraNext\Statamic\Console;

use Illuminate\Console\Command;
use SocraNext\Statamic\Content\ContentRepository;
use SocraNext\Statamic\Rendering\CodeSignature;
use SocraNext\Statamic\ServiceProvider;
use SocraNext\Statamic\Support\{Connection, Readiness, StateStore};
use Statamic\Facades\{AssetContainer, Collection};

class DoctorCommand extends Command
{
    protected $signature = 'socranext:doctor {--json : Print machine-readable diagnostics without credentials}';
    protected $description = 'Check connector configuration and report installation readiness.';

    public function handle(ContentRepository $content, Connection $connection, Readiness $readiness, StateStore $store, CodeSignature $signature): int
    {
        $storage = true;
        try { $store->get('token_hash'); } catch (\Throwable) { $storage = false; }
        $checks = [
            'storage_readable' => $storage,
            'public_https_url' => parse_url((string) config('socranext.site_url'), PHP_URL_SCHEME) === 'https',
            'connected' => $storage && $connection->connected(),
            'managed_collection' => Collection::find($content->managedCollection()) !== null,
            'asset_container' => AssetContainer::find(config('socranext.content.asset_container')) !== null,
            'signing_key' => $signature->configured(),
            'frontend_checked' => $storage && $readiness->ready(),
        ];
        $ready = !in_array(false, $checks, true);
        if ($this->option('json')) $this->line(json_encode(['version' => ServiceProvider::VERSION, 'ready' => $ready, 'checks' => $checks], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        else foreach ($checks as $name => $ok) $this->line(($ok ? 'OK   ' : 'TODO ').$name);
        return $ready ? self::SUCCESS : self::FAILURE;
    }
}
