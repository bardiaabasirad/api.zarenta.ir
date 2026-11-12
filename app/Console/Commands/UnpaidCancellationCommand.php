<?php

namespace App\Console\Commands;

use App\Jobs\UnpaidOrderCancellationJob;
use Illuminate\Console\Command;

class UnpaidCancellationCommand extends Command
{
    protected $signature = 'orders:cancel-unpaid';
    protected $description = 'Run Unpaid Cancellation Job';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        (new UnpaidOrderCancellationJob)->handle();
    }
}
