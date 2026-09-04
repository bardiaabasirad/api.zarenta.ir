<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MetalOrderUpdateRequest;
use App\Models\MetalOrder;
use App\Models\MetalOrderLog;
use App\Models\Option;
use App\Services\MetalOrderService;
use Carbon\Carbon;

class MetalOrderController extends Controller
{
    public function index()
    {
        $data = MetalOrder::query();
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');
        $status = request()->input('status');
        $trackingCode = request()->input('tracking_code');
        $orderType = request()->input('order_type');
        $startDate = request()->input('start_date');
        $endDate = request()->input('end_date');
        $creatorId = request()->input('creator_id');
        $creatorType = request()->input('creator_type');

        $data = $data->select('id', 'tracking_code', 'created_id', 'created_type', 'product', 'extra_data', 'order_type', 'status', 'created_at')
            ->when(isset($status) && $status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when(isset($trackingCode), function ($query) use ($trackingCode) {
                $query->where('tracking_code', 'like', '%' . $trackingCode . '%');
            })
            ->when(isset($creatorType), function ($query) use ($creatorType) {
                $query->where('created_type', $creatorType);
            })
            ->when(isset($creatorId), function ($query) use ($creatorId) {
                $query->where('created_id', $creatorId);
            })
            ->when(isset($orderType), function ($query) use ($orderType) {
                $query->where('order_type', $orderType);
            })
            ->when(isset($startDate) && isset($endDate), function ($query) use ($startDate, $endDate) {
                $startDate = Carbon::parse($startDate)->startOfDay();
                $endDate = Carbon::parse($endDate)->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->with(['priceSources', 'creator'])
            ->orderBy($sortBy ?? 'created_at', $dir ?? 'desc')
            ->paginate($count ?? config('app.per_page'));

        $data->getCollection()->transform(function ($order) {
            if (isset($order->product['fee']) && isset($order->product['fee_margin'])) {
                $product = $order->product;
                if ($product['tolerance_type'] == 'percentage') {
                    $product['fee'] += $product['fee'] * $product['fee_margin'] / 100;
                } else {
                    $product['fee'] += $product['fee_margin'];
                }
                unset($product['fee_margin']);
                $order->product = $product;
            }
            return $order;
        });

        return response()->json([
            'data' => $data
        ]);
    }

    public function show($order_id)
    {
        $order = MetalOrder::with([
            'priceSources',
            'creator'
        ])
            ->where('tracking_code', $order_id)->firstOrFail();

        $order->updateProductFee();

        return response()->json([
            'data' => $order,
            'cancel_reasons' => Option::where('key', 'cancel_melted_order')->get(),
        ]);
    }

    public function update(MetalOrderUpdateRequest $request, MetalOrder $order)
    {
        $order = MetalOrderService::updateWithLogging($order, $request->validated());

        return response()->json([
            'order' => $order->load(['priceSources', 'creator']),
            'message' => 'سفارش آبشده با موفقیت بروزرسانی شد',
        ]);
    }

    /**
     * تابع مخصوص تبادل ارز / سکه.
     */
    private function handleCurrencyExchange($apiClient, $order, $action, $goldPrice, $productName): void
    {
        $product = match ($productName) {
            'gold_coin_86' => 16,
            'gold_half_coin_86' => 17,
            'gold_quarter_coin_86' => 18,
            'gold_coin_old_version' => 15,
            default => null,
        };

        if (!$product) {
            return;
        }

        $common = [
            'RequestId' => \Illuminate\Support\Str::uuid()->toString(),
            'AddToExistingDateVoucher' => false,
            'AccountId' => $apiClient->kimi_account_id,
            'Date' => null,
            'Comment' => 'معامله آنلاین',
            'Action' => $action,
            'UnitPrice' => $order->product->amount * 10,
            'DivideUnitPrice' => null,
            'Quantity' => $order->quantity,
            'GoldPrice' => $goldPrice,
            'GoldUnit' => null,
        ];

        $isBuy = $order->order_type === 'buy';

        \App\Services\KimiaService::voucherExchangeCurrency(array_merge($common, [
            'SourceId' => $isBuy ? 11 : $product,
            'TargetId' => $isBuy ? $product : 11,
        ]));
    }

    public function log($id)
    {
        $logs = MetalOrderLog::where('metal_order_id', $id)
            ->with(['loggable' => function ($query) {
                $query->select('id', 'full_name');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        $groupedLogs = [];

        foreach ($logs as $log) {
            $day = $log->created_at->format('Y-m-d');

            if (!isset($groupedLogs[$day])) {
                $groupedLogs[$day] = (object)[
                    'day' => $day,
                    'flow' => []
                ];
            }

            $groupedLogs[$day]->flow[] = $log;
        }

        $result = array_values($groupedLogs);

        $transformedData = collect($result)->map(function ($day) {

            $day->flow = collect($day->flow)->groupBy(function ($flow) {
                return substr($flow['created_at'], 11, 5); // Extract the hour and minute
            })->map(function ($flows, $hour) {
                return [
                    'hour' => $hour,
                    'flows' => $flows,
                ];
            })->values()->all();

            return $day;

        })->values()->all();

        return response()->json($transformedData);
    }
}
