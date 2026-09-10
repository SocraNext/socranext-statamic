<?php

namespace SocraNext\Statamic\Console;

use Illuminate\Console\Command;
use SocraNext\Statamic\Support\Setup;

class InstallCommand extends Command
{
    protected $signature = 'socranext:install';
    protected $description = 'Prepare SocraNext article resources and public image storage without changing existing content.';

    public function handle(Setup $setup): int
    {
        $setup->prepare();
        $this->info('SocraNext articles and image storage are prepared.');
        $this->line('Connect from the SocraNext page in the Statamic control panel.');
        return self::SUCCESS;
    }
}
