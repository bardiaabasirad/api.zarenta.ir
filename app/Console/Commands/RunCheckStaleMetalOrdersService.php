<?php

namespace App\Console\Commands;

use App\Actions\MetalOrder\CheckStaleMetalOrdersAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunCheckStaleMetalOrdersService extends Command
{
    protected $signature = 'service:check-stale-metal-orders';
    protected $description = 'Persistent runner: checks stale metal orders every 5 seconds';

    private const INTERVAL_SECONDS   = 5;
    private const MEMORY_LIMIT_BYTES  = 256 * 1024 * 1024;

    private bool $running = true;

    public function handle(CheckStaleMetalOrdersAction $action): int
    {
        $this->registerSignals();

        while ($this->running) {
            $loopStart = microtime(true);

            try {
                $action->execute();
            } catch (\Throwable $e) {
                Log::channel('checkStaleMetalOrders')->error(
                    'RunCheckStaleMetalOrdersService error: ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }

            // آزادسازی حافظه بین دو iteration
            \DB::connection()->disconnect();
            gc_collect_cycles();

            // ری‌استارت پیشگیرانه (pm2 دوباره بالا می‌آورد)
            if (memory_get_usage(true) > self::MEMORY_LIMIT_BYTES) {
                $this->info('Restarting to free memory...');
                return self::SUCCESS;
            }

            // فاصله‌ی دقیق ۵ ثانیه از شروع iteration
            $elapsed = microtime(true) - $loopStart;
            $sleep   = self::INTERVAL_SECONDS - $elapsed;
            if ($sleep > 0) {
                usleep((int) ($sleep * 1_000_000));
            }
        }

        $this->info('Stopped gracefully.');
        return self::SUCCESS;
    }

    private function registerSignals(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn() => $this->running = false);
        pcntl_signal(SIGINT,  fn() => $this->running = false);
    }
}
