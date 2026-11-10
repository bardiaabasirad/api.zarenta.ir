<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Constants\MetalCoverageStatus;
use App\Http\Controllers\Controller;
use App\Models\MetalOrder;
use App\Models\MetalTreasuryLot;
use App\Models\SelectedMetalPrice;
use App\Models\Setting;
use App\Services\Metal\MetalMaturityService;
use App\Services\Metal\MetalOrderCoverageService;
use App\Services\Metal\MetalPartnerCoverageService;
use App\Services\Metal\MetalTreasuryLedgerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetalOrderCoverageController extends Controller
{
    public function __construct(
        private readonly MetalOrderCoverageService $coverage,
        private readonly MetalPartnerCoverageService $partner,
        private readonly MetalMaturityService $maturity,
        private readonly MetalTreasuryLedgerService $ledger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeCoverage();

        $query = MetalOrder::query()
            ->where('order_type', 'buy')
            ->where(function ($q) {
                $q->whereIn('coverage_status', MetalCoverageStatus::openStatuses())
                    // سفارش‌های تأییدشده قبل از اجرای موتور پوشش
                    ->orWhereNull('coverage_status');
            })
            ->where('status', '!=', 'rejected')
            ->with(['creator', 'metalItem']);

        if ($request->filled('metal_item_id')) {
            $query->where('metal_item_id', $request->integer('metal_item_id'));
        }

        if ($request->filled('coverage_status') && $request->input('coverage_status') !== 'all') {
            $query->where('coverage_status', $request->input('coverage_status'));
        }

        if ($request->filled('maturity_code')) {
            $days = match ($request->input('maturity_code')) {
                'T0' => 0,
                'T1' => 1,
                'T2' => 2,
                default => null,
            };
            if ($days !== null) {
                $query->whereHas('metalItem', fn ($q) => $q->withoutGlobalScope('visible')->where('settlement_working_days', $days));
            }
        }

        if ($request->boolean('overdue_only')) {
            $query->where('coverage_status', MetalCoverageStatus::OVERDUE);
        }

        if ($request->filled('creator_id')) {
            $query->where('created_id', $request->integer('creator_id'));
        }

        if ($request->filled('creator_type')) {
            $query->where('created_type', $request->input('creator_type'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->input('start_date'))->startOfDay(),
                Carbon::parse($request->input('end_date'))->endOfDay(),
            ]);
        }

        if ($request->filled('tracking_code')) {
            $query->where('tracking_code', 'like', '%' . $request->input('tracking_code') . '%');
        }

        $sortBy = $request->input('sortBy', 'created_at');
        $dir = $request->input('dir', 'desc');
        $count = $request->input('count') ?? config('app.per_page');

        $data = $query->orderBy($sortBy, $dir)->paginate($count);

        $data->getCollection()->transform(function (MetalOrder $order) {
            return $this->presentOpenOrderRow($order);
        });

        $t0CoverSettings = Setting::query()
            ->whereIn('option_key', [
                MetalMaturityService::SETTING_ALLOW_T0_COVER_T1,
                MetalMaturityService::SETTING_ALLOW_T0_COVER_T2,
            ])
            ->get()
            ->keyBy('option_key');

        return response()->json([
            'data' => $data,
            'allow_t0_cover_t1' => $t0CoverSettings->get(MetalMaturityService::SETTING_ALLOW_T0_COVER_T1),
            'allow_t0_cover_t2' => $t0CoverSettings->get(MetalMaturityService::SETTING_ALLOW_T0_COVER_T2),
        ]);
    }

    public function show(MetalOrder $order): JsonResponse
    {
        $this->authorizeCoverage();

        $order->load([
            'creator',
            'metalItem',
            'activeCoverageAllocations.lot',
            'coverageAttempts',
            'metalOrderExchanges',
        ]);

        $this->coverage->initializeCoverageFields($order);
        $order = $this->coverage->refreshCoverageCaches($order->fresh());

        $unrealized = $this->computeUnrealized($order);

        return response()->json([
            'data' => [
                'order' => $order,
                'maturity_code' => $this->maturity->codeForOrder($order),
                'realized_pnl' => $this->coverage->totalRealizedPnl($order),
                'unrealized_pnl' => $unrealized,
                'allocations' => $order->activeCoverageAllocations,
                'attempts' => $order->coverageAttempts()->latest()->get(),
                'compatible_lots' => $this->compatibleLots($order),
                'compatible_user_sell_lots' => $this->compatibleLots($order, 'USER_WALLET_SELL'),
            ],
        ]);
    }

    public function retryPartner(Request $request, MetalOrder $order): JsonResponse
    {
        $this->authorizeCoverage();

        $request->validate([
            'idempotency_key' => 'nullable|string|max:128',
        ]);

        $order = $this->partner->retryPartner(
            $order,
            $request->input('idempotency_key')
        );

        return response()->json([
            'message' => 'تلاش مجدد همکار انجام شد.',
            'order' => $this->presentOpenOrderRow($order->fresh(['creator', 'metalItem'])),
        ]);
    }

    public function allocateTreasury(Request $request, MetalOrder $order): JsonResponse
    {
        $this->authorizeCoverage();

        $data = $request->validate([
            'qty' => 'required|numeric|gt:0',
            'lot_id' => 'nullable|integer|exists:metal_treasury_lots,id',
            'idempotency_key' => 'nullable|string|max:128',
        ]);

        $order = $this->coverage->allocateFromTreasury(
            $order,
            (string) $data['qty'],
            $data['idempotency_key'] ?? null,
            $data['lot_id'] ?? null,
        );

        return response()->json([
            'message' => 'تخصیص از خزانه ثبت شد.',
            'order' => $this->presentOpenOrderRow($order->fresh(['creator', 'metalItem'])),
        ]);
    }

    public function allocateUserSell(Request $request, MetalOrder $order): JsonResponse
    {
        $this->authorizeCoverage();

        $data = $request->validate([
            'lot_id' => 'required|integer|exists:metal_treasury_lots,id',
            'qty' => 'required|numeric|gt:0',
            'idempotency_key' => 'nullable|string|max:128',
        ]);

        $order = $this->coverage->allocateFromUserSellLot(
            $order,
            (int) $data['lot_id'],
            (string) $data['qty'],
            $data['idempotency_key'] ?? null,
        );

        return response()->json([
            'message' => 'تخصیص فروش کاربر ثبت شد.',
            'order' => $this->presentOpenOrderRow($order->fresh(['creator', 'metalItem'])),
        ]);
    }

    public function allocatePhysical(Request $request, MetalOrder $order): JsonResponse
    {
        $this->authorizeCoverage();

        $data = $request->validate([
            'lot_id' => 'required|integer|exists:metal_treasury_lots,id',
            'qty' => 'required|numeric|gt:0',
            'idempotency_key' => 'nullable|string|max:128',
        ]);

        $order = $this->coverage->allocatePhysicalToOrder(
            $order,
            (int) $data['lot_id'],
            (string) $data['qty'],
            $data['idempotency_key'] ?? null,
        );

        return response()->json([
            'message' => 'تخصیص خرید حضوری ثبت شد.',
            'order' => $this->presentOpenOrderRow($order->fresh(['creator', 'metalItem'])),
        ]);
    }

    public function storePhysicalPurchase(Request $request): JsonResponse
    {
        $this->authorizeCoverage();

        $data = $request->validate([
            'metal_item_id' => 'required|integer|exists:metal_items,id',
            'qty' => 'required|numeric|gt:0',
            'unit_cost' => 'required|numeric|min:0',
            'document_number' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:2000',
            'idempotency_key' => 'nullable|string|max:128',
        ]);

        $purchase = $this->coverage->registerPhysicalPurchase($data);

        return response()->json([
            'message' => 'خرید حضوری در خزانه ثبت شد.',
            'data' => $purchase->load('lot'),
        ], 201);
    }

    public function externalOut(Request $request, MetalTreasuryLot $lot): JsonResponse
    {
        $this->authorizeCoverage();

        $isUserSell = $lot->source_type === \App\Constants\MetalTreasurySourceType::USER_WALLET_SELL;

        $data = $request->validate([
            'qty' => 'required|numeric|gt:0',
            'notes' => 'nullable|string|max:2000',
            'idempotency_key' => 'nullable|string|max:128',
            'sale_unit_price' => ($isUserSell ? 'required' : 'nullable') . '|numeric|min:0',
        ]);

        $result = $this->coverage->externalOutFromPhysicalLot(
            $lot->id,
            (string) $data['qty'],
            $data['idempotency_key'] ?? null,
            $data['notes'] ?? null,
            isset($data['sale_unit_price']) ? (string) $data['sale_unit_price'] : null,
        );

        $message = $isUserSell
            ? 'خروج سنتی فروش کاربر ثبت شد و سود/زیان سفارش به‌روز شد.'
            : 'خروج سنتی ثبت شد (بدون P&L).';

        return response()->json([
            'message' => $message,
            'data' => $result['lot'],
            'allocation' => $result['allocation'],
            'realized_pnl' => $result['realized_pnl'],
        ]);
    }

    public function lots(Request $request): JsonResponse
    {
        $this->authorizeCoverage();

        $query = MetalTreasuryLot::query()
            ->with('metalItem')
            ->where('status', 'OPEN')
            ->where('remaining_qty', '>', 0);

        if ($request->filled('metal_item_id')) {
            $query->where('metal_item_id', $request->integer('metal_item_id'));
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        if ($request->filled('maturity_code')) {
            $query->where('maturity_code', $request->input('maturity_code'));
        }

        $lots = $query->orderBy('purchased_at')->paginate($request->input('count') ?? 50);

        return response()->json(['data' => $lots]);
    }

    /**
     * خلاصه موجودی آزاد خزانه به تفکیک فلز.
     */
    public function treasurySummary(Request $request): JsonResponse
    {
        $this->authorizeCoverage();

        $query = MetalTreasuryLot::query()
            ->from('metal_treasury_lots')
            ->join('metal_items', 'metal_items.id', '=', 'metal_treasury_lots.metal_item_id')
            ->where('metal_treasury_lots.status', 'OPEN')
            ->where('metal_treasury_lots.remaining_qty', '>', 0)
            ->groupBy(
                'metal_treasury_lots.metal_item_id',
                'metal_items.title',
                'metal_items.unit',
                'metal_items.pricing_unit_id',
                'metal_items.settlement_working_days'
            )
            ->orderBy('metal_items.title')
            ->selectRaw('
                metal_treasury_lots.metal_item_id,
                metal_items.title as metal_title,
                metal_items.unit,
                metal_items.pricing_unit_id,
                metal_items.settlement_working_days,
                COUNT(metal_treasury_lots.id) as lots_count,
                SUM(metal_treasury_lots.remaining_qty) as available_qty,
                SUM(metal_treasury_lots.initial_qty) as initial_qty_sum
            ');

        if ($request->filled('metal_item_id')) {
            $query->where('metal_treasury_lots.metal_item_id', $request->integer('metal_item_id'));
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    private function presentOpenOrderRow(MetalOrder $order): array
    {
        $this->coverage->initializeCoverageFields($order);
        $maturity = $this->maturity->codeForOrder($order);
        $realized = $this->coverage->totalRealizedPnl($order);
        $unrealized = $this->computeUnrealized($order);

        $creator = $order->creator;

        return [
            'id' => $order->id,
            'tracking_code' => $order->tracking_code,
            'creator' => $creator,
            'creator_name' => $creator?->name
                ?? $creator?->full_name
                ?? null,
            'created_id' => $order->created_id,
            'created_type' => $order->created_type,
            'metal_item_id' => $order->metal_item_id,
            'metal_item' => $order->metalItem,
            'product_name' => $order->product_name,
            'maturity_code' => $maturity,
            'qty' => $order->qty,
            'covered_qty' => $order->covered_qty,
            'open_qty' => $order->open_qty,
            'fee' => $order->fee,
            'amount' => $order->amount,
            'realized_pnl' => $realized,
            'unrealized_pnl' => $unrealized['pnl'],
            'reference_rate' => $unrealized['rate'],
            'status' => $order->status,
            'coverage_status' => $order->coverage_status,
            'settlement_date' => $order->settlement_date,
            'coverage_deadline' => $order->coverage_deadline,
            'last_partner_error' => $order->last_partner_error,
            'last_coverage_attempt_at' => $order->last_coverage_attempt_at,
            'created_at' => $order->created_at,
        ];
    }

    private function computeUnrealized(MetalOrder $order): array
    {
        $open = (string) ($order->open_qty ?? '0');

        if (bccomp($open, '0', MetalTreasuryLedgerService::METAL_SCALE) <= 0) {
            return ['pnl' => null, 'rate' => null];
        }

        $latest = SelectedMetalPrice::query()
            ->where('metal_item_id', $order->metal_item_id)
            ->latest('id')
            ->first();

        if (!$latest || $latest->buy === null) {
            return ['pnl' => null, 'rate' => null];
        }

        $rate = $this->ledger->decimal((string) (int) $latest->buy, MetalTreasuryLedgerService::PRICE_SCALE, 'buy');
        $customer = $this->ledger->decimal((string) (int) round((float) $order->fee), MetalTreasuryLedgerService::PRICE_SCALE, 'fee');
        // mark-to-market روی کسری: اگر الان با نرخ خرید مرجع پوشش دهیم
        $pnl = bcmul($open, bcsub($customer, $rate, MetalTreasuryLedgerService::PRICE_SCALE), MetalTreasuryLedgerService::PRICE_SCALE);

        return ['pnl' => $pnl, 'rate' => $rate];
    }

    private function compatibleLots(MetalOrder $order, ?string $sourceType = null)
    {
        $maturity = $this->maturity->codeForOrder($order);
        $allowed = $this->maturity->allowedSourceMaturities($maturity);
        $sourceMetalIds = $this->maturity->allowedSourceMetalItemIds($order);

        $query = MetalTreasuryLot::query()
            ->whereIn('metal_item_id', $sourceMetalIds)
            ->whereIn('maturity_code', $allowed)
            ->where('status', 'OPEN')
            ->where('remaining_qty', '>', 0)
            ->orderBy('purchased_at');

        if ($sourceType) {
            $query->where('source_type', $sourceType);
        }

        return $query->limit(100)->get();
    }

    private function authorizeCoverage(): void
    {
        $admin = auth()->user();

        if (!$admin || !$admin->isAbleTo('manage-metal-coverage')) {
            abort(403, 'دسترسی به مدیریت پوشش خزانه ندارید.');
        }
    }
}
