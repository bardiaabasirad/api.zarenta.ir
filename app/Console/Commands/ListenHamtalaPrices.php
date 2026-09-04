<?php

namespace App\Console\Commands;

use App\Services\Hamtala\HamtalaPriceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ListenHamtalaPrices extends Command
{
    protected $signature = 'hamtala:listen-prices';
    protected $description = 'Subscribe to Hamtala Redis price channel';

    private bool $shouldStop = false;

    public function handle(HamtalaPriceService $service): int
    {
        $this->warn('LISTENER_BUILD=2026-06-18-02 pid=' . getmypid());

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, fn() => $this->shouldStop = true);
        pcntl_signal(SIGTERM, fn() => $this->shouldStop = true);

        $groupId = config('services.hamtala.group_id');
        $channel = "prices:pubsub:group:{$groupId}";

        while (!$this->shouldStop) {
            $startedAt = microtime(true);
            $client = null;
            $exitReason = 'unknown';

            try {
                $cfg = config('database.redis.hamtala');
                $this->line('--- CONFIG DUMP ---');
                $this->info('host=' . ($cfg['host'] ?? 'NULL'));
                $this->info('scheme=' . ($cfg['scheme'] ?? 'NULL'));
                $this->info('read_timeout(config)=' . ($cfg['read_timeout'] ?? 'NULL'));
                $this->info('timeout(config)=' . ($cfg['timeout'] ?? 'NULL'));
                $this->info('group_id=' . ($groupId ?? 'NULL'));
                $this->line('-------------------');

                $connection = Redis::connection('hamtala');
                $client = $connection->client();

                $client->setOption(\Redis::OPT_READ_TIMEOUT, 0);
                $client->setOption(\Redis::OPT_TCP_KEEPALIVE, 60);

                $this->info('OPT_READ_TIMEOUT(runtime)=' . $client->getOption(\Redis::OPT_READ_TIMEOUT));
                $this->info('OPT_TCP_KEEPALIVE(runtime)=' . $client->getOption(\Redis::OPT_TCP_KEEPALIVE));
                $this->info("Subscribing to {$channel} ...");

                $exitReason = 'subscribe_returned_normally';
                $client->subscribe([$channel], function ($redis, $ch, $message) use ($service) {
                    if ($this->shouldStop) {
                        $redis->close();
                        return;
                    }

                    $this->line('MSG ' . now()->toDateTimeString() . ' ch=' . $ch . ' len=' . strlen($message));

                    try {
                        $payload = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
                        $service->handle($payload);
                        $this->info('handled event=' . ($payload['event'] ?? 'unknown'));
                    } catch (\Throwable $e) {
                        $this->error('Handler error: ' . $e->getMessage());
                        report($e);
                    }
                });
            } catch (\Throwable $e) {
                $exitReason = 'exception: ' . get_class($e) . ' ' . $e->getMessage();
                $this->error('SUBSCRIBE failed: ' . $exitReason);
                report($e);
            } finally {
                try {
                    if ($client instanceof \Redis) {
                        $client->close();
                    }
                    Redis::purge('hamtala');
                } catch (\Throwable $e) {
                }

                $elapsed = microtime(true) - $startedAt;
                $this->warn(sprintf(
                    'LOOP END | reason=%s | elapsed=%.2fs | mem=%.1fMB | stop=%s',
                    $exitReason,
                    $elapsed,
                    memory_get_usage(true) / 1024 / 1024,
                    $this->shouldStop ? '1' : '0'
                ));
            }

            if ($this->shouldStop) break;
            sleep(2);
        }

        $this->info('Listener stopped gracefully.');
        return self::SUCCESS;
    }
}
