<?php

namespace App\Console\Commands;

use App\Jobs\UnpaidOrderCancellationJob;
use Illuminate\Console\Command;

class RunUnpaidCancellationJob extends Command
{
    protected $signature = 'job:unpaid-cancellation';
    protected $description = 'Run Unpaid Cancellation Job';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        (new UnpaidOrderCancellationJob)->handle();
    }
}
