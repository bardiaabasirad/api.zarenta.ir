<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Models\MetalItem;
use App\Models\MetalPhysicalDeliveryRequest;
use App\Services\Metal\MetalPhysicalDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;
use Throwable;

class MetalPhysicalDeliveryController extends Controller
{
    public function __construct(
        private readonly MetalPhysicalDeliveryService $delivery,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $trader = $request->user();

        $items = MetalPhysicalDeliveryRequest::query()
            ->with(['metalItem:id,title,unit,is_spot'])
            ->where('metal_trader_id', $trader->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('id')
            ->paginate($request->input('count') ?? 20);

        return response()->json(['data' => $items]);
    }

    public function show(Request $request, MetalPhysicalDeliveryRequest $delivery): JsonResponse
    {
        $this->assertOwner($request, $delivery);
        $delivery->load(['metalItem:id,title,unit,is_spot', 'events']);

        return response()->json(['data' => $delivery]);
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'metal_item_id' => 'required|integer|exists:metal_items,id',
            'qty' => 'required|numeric|gt:0',
        ]);

        $item = MetalItem::query()->withoutGlobalScopes()->findOrFail($data['metal_item_id']);
        if (!$item->is_spot) {
            return response()->json(['message' => 'فقط فلز حاضر قابل تحویل است.'], 422);
        }
        if (!$item->allows_physical_delivery) {
            return response()->json(['message' => 'این فلز قابلیت تحویل فیزیکی ندارد.'], 422);
        }

        try {
            $quote = $this->delivery->quoteFee($item, (string) $data['qty']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $quote,
            'meta' => [
                'fee_rate_percent_display' => $quote['fee_rate_percent'] . '٪',
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'metal_item_id' => 'required|integer|exists:metal_items,id',
            'qty' => 'required|numeric|gt:0',
            'idempotency_key' => 'nullable|string|max:128',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $created = $this->delivery->create(
                trader: $request->user(),
                metalItemId: (int) $data['metal_item_id'],
                qty: (string) $data['qty'],
                idempotencyKey: $data['idempotency_key'] ?? null,
                notes: $data['notes'] ?? null,
            );
        } catch (LogicException|InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'درخواست تحویل فیزیکی ثبت شد.',
            'data' => $created,
        ], 201);
    }

    public function cancel(Request $request, MetalPhysicalDeliveryRequest $delivery): JsonResponse
    {
        $this->assertOwner($request, $delivery);

        try {
            $updated = $this->delivery->cancelByTrader($delivery, $request->user());
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'درخواست لغو و کارمزد مسترد شد.',
            'data' => $updated,
        ]);
    }

    private function assertOwner(Request $request, MetalPhysicalDeliveryRequest $delivery): void
    {
        if ((int) $delivery->metal_trader_id !== (int) $request->user()->id) {
            abort(403, 'دسترسی به این درخواست ندارید.');
        }
    }
}
