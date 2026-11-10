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

        $autoDispatchEnabled = (bool) ($settings['auto_order_dispatch_enabled'] ?? null);
        $manualReviewTimeoutSeconds = (int) ($settings['manual_order_review_duration_seconds'] ?? 0);
        $staleThreshold = now()->subSeconds($manualReviewTimeoutSeconds + 2);

        // اگر dispatch همکار خاموش است، فقط خریدها را برای پوشش خزانه پردازش کن.
        MetalOrder::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', $staleThreshold)
            ->when(! $autoDispatchEnabled, fn ($q) => $q->where('order_type', 'buy'))
            ->select('id')
            ->chunkById(self::CHUNK_SIZE, function ($metalOrders) use ($staleThreshold) {
                foreach ($metalOrders as $metalOrder) {
                    $this->processOrder($metalOrder->id, $staleThreshold);
                }
            });
    }

    private function processOrder(int $metalOrderId, Carbon $staleThreshold): void
    {
        try {
            $metalOrder = DB::transaction(function () use ($metalOrderId, $staleThreshold) {
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

                // محدودیت وزن/فعال بودن همکار داخل موتور پوشش (خرید) یا submitClaimedSellOrder بررسی می‌شود.
                // اینجا عمداً skip نمی‌کنیم تا تطبیق خزانه/خریدهای باز همیشه انجام شود.

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
        // خرید: اول خزانه، سپس مابقی از همکار (اگر ثبت خودکار باز باشد)
        if ($metalOrder->order_type === 'buy') {
            try {
                $covered = app(\App\Services\Metal\MetalOrderCoverageService::class)
                    ->coverBuyOrder($metalOrder, 'stale-cover:' . $metalOrder->id, true);

                Log::channel('checkStaleMetalOrders')->info('Buy order coverage attempted', [
                    'metal_order_id' => $metalOrder->id,
                    'coverage_status' => $covered->coverage_status,
                    'covered_qty' => $covered->covered_qty,
                    'open_qty' => $covered->open_qty,
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

        // فروش: اول تخصیص به خریدهای باز، سپس مابقی به همکار (بدون ورود آن مابقی به خزانه)
        $this->submitClaimedSellOrder($metalOrder);
    }

    private function submitClaimedSellOrder(MetalOrder $metalOrder): void
    {
        $coverage = app(\App\Services\Metal\MetalOrderCoverageService::class);
        $service = $this->hamtalaOrderExchangeService;

        try {
            $match = $coverage->matchUserSellToOpenBuys($metalOrder->fresh());
            $matchedQty = (string) $match['matched_qty'];
            $remainderQty = (string) $match['remainder_qty'];

            Log::channel('checkStaleMetalOrders')->info('Sell matched to open buys', [
                'metal_order_id' => $metalOrder->id,
                'matched_qty' => $matchedQty,
                'remainder_qty' => $remainderQty,
            ]);

            if (bccomp($remainderQty, '0', 3) <= 0) {
                MetalOrderService::markAsSucceedAndUpdateStock(
                    $metalOrder->fresh(),
                    ['succeed_at' => Carbon::now()]
                );

                return;
            }

            // مابقی → همکار (اگر باز باشد)
            $remainderProbe = $metalOrder->fresh();
            $remainderProbe->qty = $remainderQty;
            if ($service->isAutoOrderDisabled($metalOrder) || $service->isWeightOutOfRange($remainderProbe)) {
                // همکار در دسترس نیست / وزن مابقی خارج از بازه → مابقی در خزانه می‌ماند
                MetalOrderService::markAsSucceedAndUpdateStock(
                    $metalOrder->fresh(),
                    ['succeed_at' => Carbon::now()]
                );

                return;
            }

            $order = $metalOrder->fresh();
            $extra = $order->extra_data ?? [];
            $extra['matched_to_buys_qty'] = $matchedQty;
            $extra['sell_remainder_qty'] = $remainderQty;
            $extra['partner_sell_qty'] = $remainderQty;
            $extra['auto_order_quantity'] = (float) $remainderQty;
            $order->extra_data = $extra;
            $order->save();

            $result = $service->submitAutoOrder($order->fresh());

            if ($result['status'] === 'skipped') {
                // should_confirm / محدودیت وزن: مابقی را نگه می‌داریم (بدون partner_sell_qty)
                $order = $order->fresh();
                $extra = $order->extra_data ?? [];
                unset($extra['partner_sell_qty']);
                $order->extra_data = $extra;
                $order->save();

                if ($result['success'] || bccomp($matchedQty, '0', 3) > 0) {
                    MetalOrderService::markAsSucceedAndUpdateStock(
                        $order->fresh(),
                        ['succeed_at' => Carbon::now()]
                    );
                }

                return;
            }

            if ($result['status'] === 'queued') {
                return;
            }

            Log::channel('checkStaleMetalOrders')->warning('Sell partner submit failed; keeping remainder in treasury', [
                'metal_order_id' => $metalOrder->id,
                'status' => $result['status'],
                'reason' => $result['reason'],
            ]);

            // اگر بخشی تطبیق شده، کل فروش را reject نکن — مابقی وارد خزانه می‌شود
            $order = $order->fresh();
            $extra = $order->extra_data ?? [];
            unset($extra['partner_sell_qty']);
            $order->extra_data = $extra;
            $order->save();

            if (bccomp($matchedQty, '0', 3) > 0) {
                MetalOrderService::markAsSucceedAndUpdateStock(
                    $order->fresh(),
                    ['succeed_at' => Carbon::now()]
                );

                return;
            }

            MetalOrderService::markAsRejected($order->fresh(), [
                'cause' => $result['reason'],
                'message' => 'تغییر مظنه',
                'rejected_at' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('checkStaleMetalOrders')->error('Sell coverage/partner failed', [
                'metal_order_id' => $metalOrder->id,
                'message' => $e->getMessage(),
            ]);

            $order = $metalOrder->fresh();
            $matchedQty = (string) (($order->extra_data['matched_to_buys_qty'] ?? '0'));
            $extra = $order->extra_data ?? [];
            unset($extra['partner_sell_qty']);
            $order->extra_data = $extra;
            $order->save();

            if (bccomp($matchedQty, '0', 3) > 0) {
                MetalOrderService::markAsSucceedAndUpdateStock(
                    $order->fresh(),
                    ['succeed_at' => Carbon::now()]
                );

                return;
            }

            MetalOrderService::markAsRejected($order->fresh(), [
                'cause' => 'system_error',
                'message' => 'خطای سیستمی',
                'rejected_at' => Carbon::now(),
            ]);
        }
    }
}
