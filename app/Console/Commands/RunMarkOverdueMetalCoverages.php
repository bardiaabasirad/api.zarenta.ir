<?php

namespace App\Console\Commands;

use App\Jobs\MarkOverdueMetalOrderCoveragesJob;
use Illuminate\Console\Command;

class RunMarkOverdueMetalCoverages extends Command
{
    protected $signature = 'metal-coverage:mark-overdue';

    protected $description = 'Mark open metal order coverages as OVERDUE after coverage deadline';

    public function handle(): int
    {
        (new MarkOverdueMetalOrderCoveragesJob())->handle(
            app(\App\Services\Metal\MetalOrderCoverageService::class)
        );

        $this->info('Overdue coverage job completed.');

        return self::SUCCESS;
    }
}
