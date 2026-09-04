<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Constants\AppConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\MetalOrderStoreRequest;
use App\Http\Resources\MetalOrdersResource;
use App\Jobs\CheckKimiaBalance;
use App\Models\MetalItem;
use App\Models\MetalOrder;
use App\Models\MetalTrader;
use App\Models\SelectedMetalPrice;
use App\Models\Setting;
use App\Services\MetalOrderService;
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

    public function store(MetalOrderStoreRequest $request)
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

        $persianUnit = $metalItem->unit == 'count' ? 'عدد' : 'گرم';

        $dealingGroupMetalItem = DB::table('dealing_group_metal_item')
            ->where('metal_item_id', $metal_item_id)
            ->where('dealing_group_id', $dealing_group_id)
            ->first();

        $minOrder = $dealingGroupMetalItem->min_order ?? 0.001;
        $maxOrder = $dealingGroupMetalItem->max_order ?? INF;

        // اعتبارسنجی حداقل و حداکثر مقدار سفارش
        if ($quantity < $minOrder || $quantity > $maxOrder) {
            return response()->json([
                'message' => "حداقل سفارش قابل ثبت {$minOrder} {$persianUnit} و حداکثر سفارش قابل ثبت {$maxOrder} {$persianUnit} می‌باشد",
            ]);
        }

        if ($action == 'buy') {
            $rawLatestPrice = $latestPriceObject->sell;
            $rawWantedPrice = $wantedPriceObject->sell;
            $tolerance = $dealingGroupMetalItem->sell_fee_margin;
        } else {
            $rawLatestPrice = $latestPriceObject->buy;
            $rawWantedPrice = $wantedPriceObject->buy;
            $tolerance = $dealingGroupMetalItem->buy_fee_margin;
        }

        if ($dealingGroupMetalItem->tolerance_type == 'fixed_amount') {
            $latestBasePrice = $rawLatestPrice + $tolerance;
            $wantedBasePrice = $rawWantedPrice + $tolerance;
        } else {
            $latestBasePrice = $rawLatestPrice + ($rawLatestPrice * $tolerance / 100);
            $wantedBasePrice = $rawWantedPrice + ($rawWantedPrice * $tolerance / 100);
        }

        if ($metalItem->unit == 'count') {
            if ($action == 'buy') {
                $latestPrice = roundUpToThousand($latestBasePrice);
                $wantedPrice = roundUpToThousand($wantedBasePrice);
            } else {
                $latestPrice = roundDownToThousand($latestBasePrice);
                $wantedPrice = roundDownToThousand($wantedBasePrice);
            }
        } else {
            if ($action == 'buy') {
                $latestPrice = roundUpToThousand($latestBasePrice / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR);
                $wantedPrice = roundUpToThousand($wantedBasePrice / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR);
            } else {
                $latestPrice = roundDownToThousand($latestBasePrice / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR);
                $wantedPrice = roundDownToThousand($wantedBasePrice / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR);
            }
        }

        $extraData = [
            'order_reference' => 'pnl',
            'frozen' => $frozen,
            'accounting_document_id' => $metalItem->requires_accounting_document_id ? $metalItem->accounting_document_id : null,
        ];

        // بررسی اینکه نرخ سفارش به صرفه است یا نه
        // اگر می‌خواهد بخرد و قیمت ارزان‌تر شده که چه بهتر با همان نرخی که خواسته سفارش ثبت شود
        // اگر می‌خواهد بفروشد و قیمت گران‌تر شده که چه بهتر با همان نرخی که خواسته سفارش ثبت شود

        $status = 'pending';
        $usedPrice = $rawWantedPrice;

        $totalWantedPrice = $quantity * $wantedPrice;
        $totalLatestPrice = $quantity * $latestPrice;

        $newPrice = [];

        // اگر نرخ درخواستی با آخرین نرخ برابر است => حالت عادی
        if ($totalLatestPrice != $amount) {
            // منطق رد یا پذیرش بر اساس نوع سفارش
            $isRejected = ($action === 'buy' && $totalWantedPrice < $totalLatestPrice)
                || ($action === 'sell' && $totalWantedPrice > $totalLatestPrice);

            if ($isRejected) {
                $newPrice = [
                    'new_fee' => $rawLatestPrice,
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
            'created_type' => (new MetalTrader())->getMorphClass(),
            'order_type' => $action,
            'status' => $status,
            'extra_data' => $extraData,
            'product' => [
                ...$newPrice,
                'name' => $metalItem->title,
                'metal_item_id' => $metalItem->id,
                'kimia_product_id' => $metalItem->kimia_product_id,
                'fee' => $usedPrice,
                'fee_margin' => $tolerance,
                'unit' => $metalItem->unit == 'gram' ? 'گرم' : 'عدد',
                'en_unit' => $metalItem->unit,
                'quantity' => $quantity,
                'tolerance_type' => $dealingGroupMetalItem->tolerance_type,
                'display_mode' => $dealingGroupMetalItem->display_mode,
                'amount' => $amount,
            ],
        ];

        $order = DB::transaction(function () use ($payload, $metalTrader, $amount) {

            $order = MetalOrder::create($payload);
            $order->updateProductFee();

            if ($metalTrader->trade_leverage && $metalTrader->trade_leverage > 0) {
                $order->leverageCheck()->create([
                    'status' => 'pending',
                    'applied_leverage' => $metalTrader->trade_leverage,
                    'order_value' => $amount,
                ]);
            }

            return $order;
        });

        // ✅ اینجا خارج از Transaction است
        if ($metalTrader->trade_leverage && $metalTrader->trade_leverage > 0) {
            CheckKimiaBalance::dispatch($order, $metalTrader->trade_leverage, $metalTrader->kimi_account_id);
        }

        return response()->json(['order' => MetalOrdersResource::make($order)]);
    }
}
