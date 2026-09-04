<?php

namespace App\Jobs;

use App\Services\Hamtala\HamtalaApiService;
use App\Services\Hamtala\HamtalaPriceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnsureHamtalaPriceFeedAliveJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {  }

    /**
     * Execute the job.
     */
    public function handle(
        HamtalaApiService $hamtala,
        HamtalaPriceService $hamtalaPriceService
    ): void
    {
        $lastUpdatedAt = $hamtalaPriceService->getLastUpdatedAt();

        if ($lastUpdatedAt === null) {
            $products = $hamtala->listProduct();
            $hamtalaPriceService->updateFromApiResponse($products);
            return;
        }

        $updatedAt = new \DateTime($lastUpdatedAt);
        $fiveMinutesAgo = now()->subMinutes(5);

        if ($updatedAt < $fiveMinutesAgo) {
            $products = $hamtala->listProduct();
            $hamtalaPriceService->updateFromApiResponse($products);
        }
    }
}
