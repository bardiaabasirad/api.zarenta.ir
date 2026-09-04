<?php

namespace App\Console\Commands;

use App\Jobs\CheckingMetalOrderExpiration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunMetalOrderExpirationService extends Command
{
    protected $signature = 'service:metal-order-expiration';
    protected $description = 'Run CheckingMetalOrderExpiration every 2 seconds continuously (for PM2)';

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
                Log::error('RunMetalOrderExpirationService Error: ' . $e->getMessage());
            }

            sleep(2);
        }

        $this->info('Stopped gracefully.');
    }


}
