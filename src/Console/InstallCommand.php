<?php

namespace SocraNext\Statamic\Console;

use Illuminate\Console\Command;
use SocraNext\Statamic\Content\ArticlePublisher;

class InstallCommand extends Command
{
    protected $signature = 'socranext:install';
    protected $description = 'Prepare the managed SocraNext collection and taxonomy without changing existing content.';

    public function handle(ArticlePublisher $publisher): int
    {
        $publisher->prepare();
        $this->info('SocraNext content resources are prepared.');
        $this->line('Configure your asset container and frontend layout in config/socranext.php, then connect from the Statamic control panel.');
        return self::SUCCESS;
    }
}
