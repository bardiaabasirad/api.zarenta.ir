<?php

namespace App\Actions\MetalOrder;

use App\Models\MetalOrder;
use App\Models\Setting;
use App\Services\Hamtala\HamtalaOrderExchangeService;
use App\Services\MetalOrderService;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Log;

class CheckStaleMetalOrdersAction
{
    private const CHUNK_SIZE = 200;

    public function __construct(
        private readonly HamtalaOrderExchangeService $hamtalaOrderExchangeService
    ) {}

    public function execute(): void
    {
        $settings = Setting::whereIn('option_key', [
            'auto_order_dispatch_enabled',
            'manual_order_review_duration_seconds',
        ])
            ->pluck('option_value', 'option_key');

        if (!($settings['auto_order_dispatch_enabled'] ?? null)) {
            return;
        }

        $manualReviewTimeoutSeconds = (int) ($settings['manual_order_review_duration_seconds'] ?? 0);

        $staleThreshold = now()->subSeconds($manualReviewTimeoutSeconds + 2);

        MetalOrder::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', $staleThreshold)
            ->select('id')
            ->chunkById(self::CHUNK_SIZE, function ($metalOrders) use ($staleThreshold) {
                foreach ($metalOrders as $metalOrder) {
                    $this->processOrder($metalOrder->id, $staleThreshold);
                }
            });
    }

    private function processOrder(int $metalOrderId, Carbon $staleThreshold): void
    {
        $service = $this->hamtalaOrderExchangeService;

        try {
            $metalOrder = DB::transaction(function () use ($metalOrderId, $staleThreshold, $service) {
                $metalOrder = MetalOrder::query()
                    ->whereKey($metalOrderId)
                    ->with('leverageCheck')
                    ->lockForUpdate()
                    ->first();

                if (!$metalOrder) {
                    Log::channel('checkStaleMetalOrders')->info('Skip stale order: not found', [
                        'metal_order_id' => $metalOrderId,
                    ]);

                    return null;
                }

                if ($metalOrder->status !== 'pending') {
                    Log::channel('checkStaleMetalOrders')->info('Skip stale order: status is not pending', [
                        'metal_order_id' => $metalOrder->id,
                        'status'         => $metalOrder->status,
                    ]);

                    return null;
                }

                if ($metalOrder->created_at->gt($staleThreshold)) {
                    Log::channel('checkStaleMetalOrders')->info('Skip stale order: not yet stale', [
                        'metal_order_id'   => $metalOrder->id,
                        'created_at'       => $metalOrder->created_at,
                        'stale_threshold'  => $staleThreshold,
                    ]);

                    return null;
                }

                if ($service->isWeightOutOfRange($metalOrder)) {
                    Log::channel('checkStaleMetalOrders')->info('Skip stale order: weight out of range', [
                        'metal_order_id' => $metalOrder->id,
                    ]);

                    return null;
                }

                if ($service->isAutoOrderDisabled($metalOrder)) {
                    Log::channel('checkStaleMetalOrders')->info('Skip stale order: auto order disabled', [
                        'metal_order_id' => $metalOrder->id,
                    ]);

                    return null;
                }

                if ($metalOrder->created_type == 'metal_trader') {
                    $leverageStatus = $metalOrder->leverageCheck?->status;

                    if ($leverageStatus === null) {
                        Log::channel('checkStaleMetalOrders')->info('Skip stale order: leverage check missing', [
                            'metal_order_id' => $metalOrder->id,
                        ]);

                        return null;
                    }

                    if ($leverageStatus === 'insufficient') {
                        MetalOrderService::markAsRejected($metalOrder, [
                            'cause'       => 'insufficient',
                            'message'     => 'نداشتن ته حساب کافی',
                            'rejected_at' => Carbon::now(),
                        ]);

                        Log::channel('checkStaleMetalOrders')->info('Stale order rejected: insufficient leverage', [
                            'metal_order_id' => $metalOrder->id,
                        ]);

                        return null;
                    }

                    if ($leverageStatus === 'pending') {
                        Log::channel('checkStaleMetalOrders')->info('Skip stale order: leverage check pending', [
                            'metal_order_id' => $metalOrder->id,
                        ]);

                        return null;
                    }
                }

                // مهم‌ترین بخش: قبل از dispatch، سفارش از pending خارج می‌شود.
                $metalOrder->status = 'processing';
                $metalOrder->save();

                if ($metalOrder->created_type == 'metal_trader') {
                    return $metalOrder->fresh('leverageCheck');
                } else {
                    return $metalOrder;
                }
            });

            if (!$metalOrder) {
                return;
            }

            $this->submitClaimedOrder($metalOrder);
        } catch (\Throwable $e) {
            Log::channel('checkStaleMetalOrders')->error('Failed to claim stale metal order', [
                'metal_order_id' => $metalOrderId,
                'message'        => $e->getMessage(),
            ]);
        }
    }

    private function submitClaimedOrder(MetalOrder $metalOrder): void
    {
        $service = $this->hamtalaOrderExchangeService;

        try {
            $result = $service->submitAutoOrder($metalOrder);

            if ($result['status'] === 'skipped') {
                if ($result['success']) {
                    MetalOrderService::markAsSucceedAndUpdateStock(
                        $metalOrder,
                        ['succeed_at' => Carbon::now()]
                    );
                }

                return;
            }

            if ($result['status'] === 'queued') {
                return;
            }

            Log::channel('checkStaleMetalOrders')->warning('Auto order rejected', [
                'metal_order_id' => $metalOrder->id,
                'status'         => $result['status'],
                'reason'         => $result['reason'],
            ]);

            MetalOrderService::markAsRejected($metalOrder, [
                'cause'       => $result['reason'],
                'message'     => 'تغییر مظنه',
                'rejected_at' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('checkStaleMetalOrders')->error('Hamtala submit failed', [
                'metal_order_id' => $metalOrder->id,
                'message'        => $e->getMessage(),
            ]);

            MetalOrderService::markAsRejected($metalOrder, [
                'cause'       => 'system_error',
                'message'     => 'خطای سیستمی',
                'rejected_at' => Carbon::now(),
            ]);
        }
    }
}
