<?php

namespace App\Console\Commands;

use App\Jobs\GetMarketPriceJob;
use Exception as ExceptionAlias;
use Illuminate\Console\Command;

class MarketPriceCommand extends Command
{
    protected $signature = 'market:fetch-price';
    protected $description = 'Run Market Price Job';

    /**
     * @throws ExceptionAlias
     */
    public function handle(): void
    {
        (new GetMarketPriceJob)->handle();
    }
}
