<?php

namespace App\Jobs;

use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * تعداد دفعات تلاش مجدد
     */
    public int $tries = 5;

    /**
     * فاصله زمانی بین هر تلاش (ثانیه)
     */
    public int $backoff = 4;

    protected string $phone;
    protected string $pattern;
    protected array $parameters;

    /**
     * Create a new job instance.
     */
    public function __construct(string $phone, string $pattern, array $parameters)
    {
        $this->phone = $phone;
        $this->pattern = $pattern;
        $this->parameters = $parameters;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $response = SmsService::sendPattern(
                fromNumber: '+983000505',
                patternCode: $this->pattern,
                recipient: $this->phone,
                params: $this->parameters
            );
        } catch (Throwable $e) {
            // لاگ کامل خطا
            Log::error('SMS sending failed', [
                'phone' => $this->phone,
                'pattern' => $this->pattern,
                'parameters' => $this->parameters,
                'attempt' => $this->attempts(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            throw $e; // برای retry خودکار queue
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical('SMS job failed after all retries', [
            'phone' => $this->phone,
            'pattern' => $this->pattern,
            'parameters' => $this->parameters,
            'final_error' => $exception->getMessage()
        ]);
    }
}
