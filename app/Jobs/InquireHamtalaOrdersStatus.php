<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class InquireHamtalaOrdersStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('hamtala');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting Hamtala orders status inquiry job');

            $exitCode = Artisan::call('hamtala:inquire-orders-status', [
                '--limit' => 50,
                '--status' => 'placed',
            ]);

            if ($exitCode === 0) {
                Log::info('Hamtala orders status inquiry job completed successfully');
            } else {
                Log::warning('Hamtala orders status inquiry job completed with errors', [
                    'exit_code' => $exitCode,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Hamtala orders status inquiry job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Hamtala orders status inquiry job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
