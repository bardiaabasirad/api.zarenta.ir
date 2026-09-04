<?php

namespace App\Console\Commands;

use App\Jobs\EnsureHamtalaPriceFeedAliveJob;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class CheckHamtalaPriceFeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hamtala:check-price-feed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Hamtala price feed health and reconnect if no updates were received';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        EnsureHamtalaPriceFeedAliveJob::dispatchSync();

        return CommandAlias::SUCCESS;
    }
}
