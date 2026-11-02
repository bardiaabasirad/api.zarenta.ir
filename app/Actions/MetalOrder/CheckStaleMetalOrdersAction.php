<?php

namespace App\Actions\MetalOrder;

use App\Models\MetalOrder;
use App\Models\MetalTrader;
use App\Models\Setting;
use App\Services\Hamtala\HamtalaOrderExchangeService;
use App\Services\Metal\MetalOrderAssetRiskService;
use App\Services\MetalOrderService;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Log;

class CheckStaleMetalOrdersAction
{
    private const CHUNK_SIZE = 200;

    public function __construct(
        private readonly HamtalaOrderExchangeService $hamtalaOrderExchangeService,
        private readonly MetalOrderAssetRiskService $assetRiskService
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
                    ->with(['leverageCheck', 'creator'])
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
                    $trader = $metalOrder->creator instanceof MetalTrader
                        ? $metalOrder->creator
                        : MetalTrader::query()->find($metalOrder->created_id);

                    $assetRisk = $this->assetRiskService->assess($metalOrder, $trader);

                    if ($assetRisk && ! $assetRisk['is_sufficient']) {
                        MetalOrderService::markAsRejected($metalOrder, [
                            'cause' => 'insufficient_asset_ratio',
                            'message' => 'کمبود نسبت دارایی به مبلغ سفارش',
                            'rejected_at' => Carbon::now(),
                            'asset_risk' => $assetRisk,
                        ]);

                        Log::channel('checkStaleMetalOrders')->info('Stale order rejected: insufficient asset ratio', [
                            'metal_order_id' => $metalOrder->id,
                            'asset_risk' => $assetRisk,
                        ]);

                        return null;
                    }

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
        // خرید: موتور پوشش خزانه/همکار (کسری باز؛ بدون auto-reject روی شکست همکار)
        if ($metalOrder->order_type === 'buy') {
            try {
                app(\App\Services\Metal\MetalOrderCoverageService::class)
                    ->coverBuyOrder($metalOrder, 'stale-cover:' . $metalOrder->id, true);

                Log::channel('checkStaleMetalOrders')->info('Buy order coverage attempted', [
                    'metal_order_id' => $metalOrder->id,
                    'coverage_status' => $metalOrder->fresh()->coverage_status,
                    'open_qty' => $metalOrder->fresh()->open_qty,
                ]);
            } catch (\Throwable $e) {
                Log::channel('checkStaleMetalOrders')->error('Coverage engine failed', [
                    'metal_order_id' => $metalOrder->id,
                    'message' => $e->getMessage(),
                ]);
                // سفارش reject نمی‌شود؛ کسری برای ادمین باز می‌ماند
                try {
                    $coverage = app(\App\Services\Metal\MetalOrderCoverageService::class);
                    $coverage->initializeCoverageFields($metalOrder->fresh());
                    $order = $coverage->refreshCoverageCaches($metalOrder->fresh());
                    $order->last_partner_error = mb_substr($e->getMessage(), 0, 500);
                    $order->last_coverage_attempt_at = Carbon::now();
                    $order->save();
                } catch (\Throwable $inner) {
                    Log::channel('checkStaleMetalOrders')->error('Failed to persist coverage error', [
                        'metal_order_id' => $metalOrder->id,
                        'message' => $inner->getMessage(),
                    ]);
                }
            }

            return;
        }

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
