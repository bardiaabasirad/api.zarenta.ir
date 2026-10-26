<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Http\Requests\MetalOrderStoreRequest;
use App\Http\Resources\MetalOrdersResource;
use App\Models\MetalItem;
use App\Models\MetalOrder;
use App\Models\MetalTrader;
use App\Models\SelectedMetalPrice;
use App\Models\Setting;
use App\Services\MarketHolidayService;
use App\Services\MetalOrderService;
use App\Services\MetalPriceQuoteService;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index()
    {
        $data = MetalOrder::where('created_type', (new MetalTrader())->getMorphClass())
            ->where('created_id', Auth::id());
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count') ?? config('app.per_page');
        $status = request()->input('status');
        $trackingCode = request()->input('tracking_code');
        $start_date = request()->input('start_date');
        $end_date = request()->input('end_date');

        $data = $data->select('id', 'product', 'order_type', 'status', 'tracking_code', 'extra_data', 'created_at')
            ->when(isset($status) && $status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when(isset($trackingCode), function ($query) use ($trackingCode) {
                $query->where('id', 'like', '%' . $trackingCode . '%')
                    ->orWhere('tracking_code', 'like', '%' . $trackingCode . '%');
            })
            ->when(isset($start_date) && isset($end_date), function ($query) use ($start_date, $end_date) {
                $startDate = Carbon::parse($start_date)->startOfDay();
                $endDate = Carbon::parse($end_date)->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->orderBy($sortBy ?? 'created_at', $dir ?? 'desc')
            ->paginate($count)
            ->through(fn($order) => $order->updateProductFee());

        MetalOrdersResource::collection($data);

        return response()->json([
            'data' => $data,
            'now' => Carbon::now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'expiration_time' => Setting::where('option_key', 'validity_period_of_melted_order_before_expires')->first()->option_value,
        ]);
    }

    public function show($tracking_code)
    {
        $order = MetalOrder::where('tracking_code', $tracking_code)->first();

        $order->updateProductFee();

        return \response()->json([
            'order' => MetalOrdersResource::make($order),
        ]);
    }

    public function store(MetalOrderStoreRequest $request, MetalPriceQuoteService $quoteService)
    {
        $metalTrader = Auth::guard('metal-trader-api')->user();
        $quantity = $request->quantity;
        $amount = $request->amount;
        $metal_item_id = $request->metal_item_id;
        $dealing_group_id = $request->dealing_group_id;
        $frozen = $request->frozen;
        $action = $request->action;

        $wantedPriceObject = SelectedMetalPrice::find($request->selected_metal_price_id);
        $latestPriceObject = SelectedMetalPrice::where('metal_item_id', $metal_item_id)->latest()->first();

        $metalItem = MetalItem::find($metal_item_id);

        $settlementDate = MarketHolidayService::getSettlementDateForMetalItem($metalItem);

        $persianUnit = $metalItem->unit == 'count' ? 'عدد' : 'گرم';

        $dealingGroupMetalItem = DB::table('dealing_group_metal_item')
            ->where('metal_item_id', $metal_item_id)
            ->where('dealing_group_id', $dealing_group_id)
            ->first();

        $minOrder = $dealingGroupMetalItem->min_order ?? 0.001;
        $maxOrder = $dealingGroupMetalItem->max_order ?? INF;

        if ($quantity < $minOrder || $quantity > $maxOrder) {
            return response()->json([
                'message' => "حداقل سفارش قابل ثبت {$minOrder} {$persianUnit} و حداکثر سفارش قابل ثبت {$maxOrder} {$persianUnit} می‌باشد",
            ]);
        }

        $latestQuote = $quoteService->quote($latestPriceObject, $dealingGroupMetalItem, $action, $metalItem);
        $wantedQuote = $quoteService->quote($wantedPriceObject, $dealingGroupMetalItem, $action, $metalItem);

        $extraData = [
            'order_reference' => 'pnl',
            'frozen' => $frozen,
        ];

        $status = 'pending';
        $newPrice = [];

        $totalWantedPrice = (int) round($quantity * $wantedQuote['rounded']);
        $totalLatestPrice = (int) round($quantity * $latestQuote['rounded']);

        if ($totalLatestPrice != $amount) {
            $isRejected = ($action === 'buy' && $totalWantedPrice < $totalLatestPrice)
                || ($action === 'sell' && $totalWantedPrice > $totalLatestPrice);

            if ($isRejected) {
                $newPrice = [
                    'new_fee' => $latestQuote['fee'],
                    'new_display_fee' => $latestQuote['displayed'],
                ];
                $extraData = array_merge($extraData, [
                    'cause' => 'price_difference',
                    'rejected_at' => now(),
                    'message' => 'بروز نبودن نرخ',
                    'current_price' => $totalLatestPrice,
                    'requested_price' => $totalWantedPrice,
                ]);

                $status = 'rejected';
            }
        }

        $payload = [
            'tracking_code' => MetalOrderService::generateTrackingCode(),
            'created_id' => $metalTrader->id,
            'metal_item_id' => $metalItem->id,
            'created_type' => (new MetalTrader())->getMorphClass(),
            'order_type' => $action,
            'status' => $status,
            'extra_data' => $extraData,
            'settlement_date' => $settlementDate,
            'settlement_status' => $settlementDate->isAfter(today()) ? 'pending' : 'settled',
            'product' => [
                ...$newPrice,
                'name' => $metalItem->title,
                'metal_item_id' => $metalItem->id,
                'fee' => $wantedQuote['fee'],
                'display_fee' => $wantedQuote['displayed'],
                'fee_margin' => 0,
                'unit' => $persianUnit,
                'en_unit' => $metalItem->unit,
                'quantity' => $quantity,
                'tolerance_type' => $dealingGroupMetalItem->tolerance_type,
                'pricing_unit_id' => $wantedQuote['pricing_unit_id'],
                'target_unit_id' => $wantedQuote['target_unit_id'],
                'target_unit_name' => $wantedQuote['target_unit_name'],
                'display_mode' => $wantedQuote['display_mode'],
                'amount' => $amount,
            ],
        ];

        $order = DB::transaction(function () use ($payload) {
            $order = MetalOrder::create($payload);
            $order->updateProductFee();

            return $order;
        });

        return response()->json(['order' => MetalOrdersResource::make($order)]);
    }
}
