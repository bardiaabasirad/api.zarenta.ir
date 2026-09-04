<?php

namespace App\Jobs;

use App\Services\MetalPriceIngestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IngestHamtalaPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * تعداد دفعات تلاش مجدد در صورت خطا
     */
    public int $tries = 3;

    /**
     * حداکثر زمان اجرای job (ثانیه)
     */
    public int $timeout = 60;

    /**
     * @param array $products لیست محصولات نرمال‌شده برای ingestion
     */
    public function __construct(
        private readonly array $products
    ) {}

    public function handle(MetalPriceIngestionService $ingestionService): void
    {
        $ingestionService->ingestFromHamtala($this->products);
    }
}
