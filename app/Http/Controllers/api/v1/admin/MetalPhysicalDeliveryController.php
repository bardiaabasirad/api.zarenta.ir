<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Constants\MetalPhysicalDeliverySource;
use App\Constants\MetalPhysicalDeliveryStatus;
use App\Constants\MetalTreasuryLotStatus;
use App\Constants\MetalTreasurySourceType;
use App\Http\Controllers\Controller;
use App\Models\MetalPhysicalDeliveryRequest;
use App\Models\MetalTreasuryLot;
use App\Services\Metal\MetalPhysicalDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;

class MetalPhysicalDeliveryController extends Controller
{
    public function __construct(
        private readonly MetalPhysicalDeliveryService $delivery,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeDelivery();

        $query = MetalPhysicalDeliveryRequest::query()
            ->with([
                'metalItem:id,title,unit,is_spot',
                'trader:id,name,phone',
            ])
            ->orderByDesc('id');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('tracking_code')) {
            $query->where('tracking_code', 'like', '%' . $request->input('tracking_code') . '%');
        }

        if ($request->filled('metal_item_id')) {
            $query->where('metal_item_id', $request->integer('metal_item_id'));
        }

        if ($request->filled('metal_trader_id')) {
            $query->where('metal_trader_id', $request->integer('metal_trader_id'));
        }

        $page = $query->paginate($request->input('count') ?? 50);

        return response()->json(['data' => $page]);
    }

    public function show(MetalPhysicalDeliveryRequest $delivery): JsonResponse
    {
        $this->authorizeDelivery();

        $delivery->load([
            'metalItem',
            'trader:id,name,phone',
            'wallet',
            'treasuryLot',
            'events',
        ]);

        $compatibleLots = [];
        if (in_array($delivery->status, MetalPhysicalDeliveryStatus::cancellable(), true)
            || $delivery->status === MetalPhysicalDeliveryStatus::PENDING
            || $delivery->status === MetalPhysicalDeliveryStatus::READY_FOR_PICKUP
        ) {
            // Lotهای با موجودی آزاد؛ اعتبارسنجی وزن واقعی در deliver انجام می‌شود
            // (ممکن است وزن تحویلی اندکی بیشتر از درخواست باشد)
            $compatibleLots = MetalTreasuryLot::query()
                ->where('metal_item_id', $delivery->metal_item_id)
                ->where('source_type', MetalTreasurySourceType::PHYSICAL_PURCHASE)
                ->where('status', MetalTreasuryLotStatus::OPEN)
                ->where('remaining_qty', '>', 0)
                ->orderBy('purchased_at')
                ->get(['id', 'remaining_qty', 'initial_qty', 'unit_cost', 'purchased_at', 'maturity_code', 'source_type']);
        }

        return response()->json([
            'data' => $delivery,
            'compatible_lots' => $compatibleLots,
            'sources' => MetalPhysicalDeliverySource::all(),
        ]);
    }

    public function markReady(MetalPhysicalDeliveryRequest $delivery): JsonResponse
    {
        $this->authorizeDelivery();

        try {
            $updated = $this->delivery->markReady($delivery);
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'درخواست آماده تحویل شد.',
            'data' => $updated,
        ]);
    }

    public function cancel(Request $request, MetalPhysicalDeliveryRequest $delivery): JsonResponse
    {
        $this->authorizeDelivery();

        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $updated = $this->delivery->cancelByAdmin($delivery, $data['reason'] ?? null);
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'درخواست لغو و کارمزد مسترد شد.',
            'data' => $updated,
        ]);
    }

    public function reject(Request $request, MetalPhysicalDeliveryRequest $delivery): JsonResponse
    {
        $this->authorizeDelivery();

        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $updated = $this->delivery->reject($delivery, $data['reason'] ?? null);
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'درخواست رد و کارمزد مسترد شد.',
            'data' => $updated,
        ]);
    }

    public function deliver(Request $request, MetalPhysicalDeliveryRequest $delivery): JsonResponse
    {
        $this->authorizeDelivery();

        $data = $request->validate([
            'source' => 'required|in:' . implode(',', MetalPhysicalDeliverySource::all()),
            'lot_id' => 'nullable|integer|exists:metal_treasury_lots,id',
            'delivered_qty' => 'nullable|numeric|gt:0',
            'notes' => 'nullable|string|max:2000',
            'idempotency_key' => 'nullable|string|max:128',
        ]);

        try {
            $updated = $this->delivery->deliver($delivery, $data);
        } catch (LogicException|InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تحویل فیزیکی ثبت شد.',
            'data' => $updated,
        ]);
    }

    public function feeSettings(): JsonResponse
    {
        $this->authorizeDelivery();

        $rate = $this->delivery->feeRatePercent();

        return response()->json([
            'data' => [
                'fee_rate_percent' => $rate,
                'fee_rate_percent_display' => $rate . '٪',
            ],
        ]);
    }

    public function updateFeeSettings(Request $request): JsonResponse
    {
        $this->authorizeDelivery();

        $data = $request->validate([
            'fee_rate_percent' => 'required|numeric|min:0|max:100',
        ]);

        $rate = $this->delivery->updateFeeRatePercent((string) $data['fee_rate_percent']);

        return response()->json([
            'message' => 'نرخ کارمزد به‌روزرسانی شد. فقط درخواست‌های جدید تحت تأثیر قرار می‌گیرند.',
            'data' => [
                'fee_rate_percent' => $rate,
                'fee_rate_percent_display' => $rate . '٪',
            ],
        ]);
    }

    private function authorizeDelivery(): void
    {
        $admin = auth()->user();
        if (!$admin || !$admin->isAbleTo('manage-metal-physical-delivery')) {
            abort(403, 'دسترسی به مدیریت تحویل فیزیکی ندارید.');
        }
    }
}
