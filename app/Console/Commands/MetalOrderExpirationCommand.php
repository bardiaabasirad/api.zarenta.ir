<?php

namespace App\Console\Commands;

use App\Jobs\CheckingMetalOrderExpiration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MetalOrderExpirationCommand extends Command
{
    protected $signature = 'metal-orders:expire';
    protected $description = 'Run CheckingMetalOrderExpiration every 2 seconds continuously';

    /*
     * سفارش‌های pending که مهلت اعتبارشان تمام شده را منقضی و reject می‌کند.
     */

    public function handle(): void
    {
        $running = true;

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function () use (&$running) { $running = false; });
        pcntl_signal(SIGINT,  function () use (&$running) { $running = false; });

        while ($running) {
            try {
                (new CheckingMetalOrderExpiration)->handle();
            } catch (\Throwable $e) {
                Log::error('MetalOrderExpirationCommand Error: ' . $e->getMessage());
            }

            sleep(2);
        }

        $this->info('Stopped gracefully.');
    }


}
