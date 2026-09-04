<?php

namespace App\Console\Commands;

use App\Jobs\GetMarketPriceJob;
use Exception as ExceptionAlias;
use Illuminate\Console\Command;

class RunMarketPriceJob extends Command
{
    protected $signature = 'job:market-price';
    protected $description = 'Run Market Price Job';

    /**
     * @throws ExceptionAlias
     */
    public function handle(): void
    {
        (new GetMarketPriceJob)->handle();
    }
}
