<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\MetalOrder;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Morilog\Jalali\Jalalian;

class DashboardController extends Controller
{
    public function badges()
    {
        return response()->json([
            [
                'type' => 'metal_order',
                'count' => MetalOrder::where('status', 'pending')->count(),
            ]
        ]);
    }

    public function notifications()
    {
        $adminId = auth()->id();

        $notifications = Notification::where('is_active', true)
            ->whereDoesntHave('reads', function($query) use ($adminId) {
                $query->where('admin_id', $adminId);
            })
            ->select(['id', 'title', 'message', 'created_at'])
            ->get();

        return response()->json($notifications);
    }

    public function all()
    {
        list($now, $thirtyDaysAgo, $beginOfThisMonth, $beginOfPastMonth) = $this->getDates();

        // اگر امروز بیستم باشد از اول ماه تا بیستم ماه جاری با همین بازه از ماه قبل مقایسه می‌شود

        // تعداد کاربران
        list($periodOfThisMonthUserCount, $periodOfPreviousMonthUserCount) = $this->userStatistics($beginOfThisMonth, $now, $beginOfPastMonth, $thirtyDaysAgo);
        $usersPercentageChange = $this->getDifferentInPercentage($periodOfPreviousMonthUserCount, $periodOfThisMonthUserCount);
        // تعداد سفارش‌ها
        list($periodOfThisMonthOrdersCount, $periodOfPreviousMonthOrdersCount) = $this->orderCountStatistics($beginOfThisMonth, $now, $beginOfPastMonth, $thirtyDaysAgo);
        $ordersCountPercentageChange = $this->getDifferentInPercentage($periodOfPreviousMonthOrdersCount, $periodOfThisMonthOrdersCount);
        // مبلغ سفارشات
        list($periodOfThisMonthOrdersCost, $periodOfPreviousMonthOrdersCost) = $this->orderCostStatistics($beginOfThisMonth, $now, $beginOfPastMonth, $thirtyDaysAgo);
        $ordersCostPercentageChange = $this->getDifferentInPercentage($periodOfPreviousMonthOrdersCost, $periodOfThisMonthOrdersCost);
        // سود سفارش‌ها
        list($periodOfThisMonthOrdersProfit, $periodOfPreviousMonthOrdersProfit) = $this->orderProfitStatistics($beginOfThisMonth, $now, $beginOfPastMonth, $thirtyDaysAgo);
        $ordersProfitPercentageChange = $this->getDifferentInPercentage($periodOfPreviousMonthOrdersProfit, $periodOfThisMonthOrdersProfit);

        // Chart Data
        $thirtyDaysAgoOrdersCount = $this->getThirtyDaysAgoOrdersCount($thirtyDaysAgo, $now);
        $thirtyDaysAgoOrdersCosts = $this->getThirtyDaysAgoOrdersCosts($thirtyDaysAgo, $now);
        $thirtyDaysAgoOrdersProfits = $this->getThirtyDaysAgoOrdersProfits($thirtyDaysAgo, $now);
        $thirtyDaysAgoUsersCount = $this->getThirtyDaysAgoUsersCount($thirtyDaysAgo, $now);

        $orders = Order::latest()
            ->with('user:id,full_name')
            ->select('id','code','status','user_id','preferred_shipping_method')
            ->limit(7)
            ->get();

        $metalOrders = MetalOrder::with(['creator'])
            ->latest()
            ->limit(7)
            ->get()
            ->map(function ($order) {

                $product = $order->product;
                $creator = $order->creator;

                return [
                    'id'             => $order->id,
                    'tracking_code'  => $order->tracking_code,
                    'order_type'     => $order->order_type,
                    'created_type'   => $order->created_type,
                    'status'         => $order->status,

                    'product' => [
                        'name'      => data_get($product, 'name'),
                        'quantity'  => data_get($product, 'quantity'),
                        'unit'      => data_get($product, 'unit'),
                    ],

                    'creator' => array_filter([
                        'id'   => data_get($creator, 'id'),
                        'name' => data_get($creator, 'full_name') ?? data_get($creator, 'name'),
                        'code' => data_get($creator, 'code'), // اگر نبود حذف می‌شود
                    ], fn ($v) => !is_null($v)),
                ];
            });

        return response()->json([
            'thisMonthUserCount' => $periodOfThisMonthUserCount??0,
            'pastMonthUserCount' => $periodOfPreviousMonthUserCount??0,
            'usersPercentageChange' => $usersPercentageChange,

            'thisMonthOrderCount' => $periodOfThisMonthOrdersCount??0,
            'pastMonthOrderCount' => $periodOfPreviousMonthOrdersCount??0,
            'ordersCountPercentageChange' => $ordersCountPercentageChange,

            'thisMonthOrdersCosts' => $periodOfThisMonthOrdersCost??0,
            'pastMonthOrdersCosts' => $periodOfPreviousMonthOrdersCost??0,
            'ordersCostPercentageChange' => $ordersCostPercentageChange,

            'periodOfThisMonthOrdersProfit' => $periodOfThisMonthOrdersProfit??0,
            'periodOfPreviousMonthOrdersProfit' => $periodOfPreviousMonthOrdersProfit??0,
            'ordersProfitPercentageChange' => $ordersProfitPercentageChange,


            'thirtyDaysAgoUsersCount' => array_values($thirtyDaysAgoUsersCount->toArray()),
            'thirtyDaysAgoOrdersCount' => array_values($thirtyDaysAgoOrdersCount->toArray()),
            'thirtyDaysAgoOrdersCosts' => array_values($thirtyDaysAgoOrdersCosts->toArray()),
            'thirtyDaysAgoOrdersProfits' => array_values($thirtyDaysAgoOrdersProfits->toArray()),

            'orders' => $orders,
            'metal_orders' => $metalOrders,
        ]);
    }

    /**
     * @param $prevValue
     * @param $nowValue
     * @return float|int
     */
    private function getDifferentInPercentage($prevValue, $nowValue)
    {
        $percentage = 0;
        if ($prevValue && $prevValue != 0) {
            $percentage = (($nowValue - $prevValue) / $prevValue) * 100;
        }
        return floor($percentage);
    }

    /**
     * @param $data
     * @return Collection
     */
    private function prepareDataByMonth($data)
    {
        $allMonths = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthName = Jalalian::fromCarbon(Carbon::create(null, $i))->format('F');
            $allMonths[$monthName] = [
                'xAxis' => $monthName,
                'yAxis' => 0,
            ];
        }

        // Merge the data from the query with the array of all months
        $data = collect($allMonths)->merge($data)->values();
        // Past year month name
        $currentMonth = Jalalian::now()->getFirstDayOfMonth()->addMonths()->subYears()->format('F');

        // Find the index of the item with the specified "month" value
        $indexToMove = $data->search(function ($item) use ($currentMonth) {
            return $item['xAxis'] === $currentMonth;
        });

        // If the item is found, reorder the collection
        if ($indexToMove !== false) {
            $itemsToMove = $data->splice($indexToMove)->toArray(); // Remove items and convert to array
            $data = collect($itemsToMove)->concat($data);
        }
        return $data;
    }

    /**
     * @param $data
     * @return Collection
     */
    private function prepareDataByDay($data)
    {
        // Get the current date and 30 days ago
        $currentDate = Carbon::now();
        $thirtyDaysAgo = $currentDate->copy()->subDays(30);

        // Generate an array of days within the 30-day period
        $allDays = [];
        while ($thirtyDaysAgo <= $currentDate) {
            $dayName = Jalalian::fromCarbon($thirtyDaysAgo)->format('m/d');
            $allDays[$dayName] = [
                'xAxis' => $dayName,
                'yAxis' => 0,
            ];
            $thirtyDaysAgo->addDay();
        }

        foreach ($allDays as $key => $day) {
            if (array_key_exists($key, $data->toArray())) {
                $allDays[$key] = $data->get($key);
            }
        }

        // Sort the data by day
        return collect($allDays)->sortBy('xAxis');
    }

    /**
     * @param Carbon $beginOfThisMonth
     * @param Carbon $now
     * @param Carbon $beginOfPastMonth
     * @param Carbon $thirtyDaysAgo
     * @return array
     */
    private function userStatistics(Carbon $beginOfThisMonth, Carbon $now, Carbon $beginOfPastMonth, Carbon $thirtyDaysAgo): array
    {
        $periodOfThisMonthUserCount = User::completedRegistered()
            ->whereBetween('created_at', [$beginOfThisMonth, $now])
            ->count();
        $periodOfPreviousMonthUserCount = User::completedRegistered()
            ->whereBetween('created_at', [$beginOfPastMonth, $thirtyDaysAgo])
            ->count();
        return array($periodOfThisMonthUserCount, $periodOfPreviousMonthUserCount);
    }

    /**
     * @param Carbon $beginOfThisMonth
     * @param Carbon $now
     * @param Carbon $beginOfPastMonth
     * @param Carbon $thirtyDaysAgo
     * @return array
     */
    private function orderCountStatistics(Carbon $beginOfThisMonth, Carbon $now, Carbon $beginOfPastMonth, Carbon $thirtyDaysAgo): array
    {
        $periodOfThisMonthOrdersCount = Order::acceptedInCalculations()
            ->whereBetween('created_at', [$beginOfThisMonth, $now])
            ->count();
        $periodOfPreviousMonthOrdersCount = Order::acceptedInCalculations()
            ->whereBetween('created_at', [$beginOfPastMonth, $thirtyDaysAgo])
            ->count();
        return array($periodOfThisMonthOrdersCount, $periodOfPreviousMonthOrdersCount);
    }

    /**
     * @param Carbon $beginOfThisMonth
     * @param Carbon $now
     * @param Carbon $beginOfPastMonth
     * @param Carbon $thirtyDaysAgo
     * @return array
     */
    private function orderCostStatistics(Carbon $beginOfThisMonth, Carbon $now, Carbon $beginOfPastMonth, Carbon $thirtyDaysAgo): array
    {
        $periodOfThisMonthOrdersCost = Order::acceptedInCalculations()
            ->whereBetween('orders.created_at', [$beginOfThisMonth, $now])
            ->sum('sale');
        $periodOfPreviousMonthOrdersCost = Order::acceptedInCalculations()
            ->whereBetween('orders.created_at', [$beginOfPastMonth, $thirtyDaysAgo])
            ->sum('sale');
        return array($periodOfThisMonthOrdersCost, $periodOfPreviousMonthOrdersCost);
    }

    /**
     * @param Carbon $beginOfThisMonth
     * @param Carbon $now
     * @param Carbon $beginOfPastMonth
     * @param Carbon $thirtyDaysAgo
     * @return array
     */
    private function orderProfitStatistics(Carbon $beginOfThisMonth, Carbon $now, Carbon $beginOfPastMonth, Carbon $thirtyDaysAgo): array
    {
        $periodOfThisMonthOrdersProfit = Order::acceptedInCalculations()
            ->whereBetween('orders.created_at', [$beginOfThisMonth, $now])
            ->sum('registered_melted_gold_order_total_profit');
//            ->sum(DB::raw('CAST(sale AS SIGNED) - CAST(purchase AS SIGNED)'));  // چونکه ستون sale و purchase از نوع دسیمال و بدون علامت هست و ممکن است نتیجه منفی شود برای جلوگیری از این مشکل باید ابتدا ستون را cast کنیم

        $periodOfPreviousMonthOrdersProfit = Order::acceptedInCalculations()
            ->whereBetween('orders.created_at', [$beginOfPastMonth, $thirtyDaysAgo])
            ->sum('registered_melted_gold_order_total_profit');
//            ->sum(DB::raw('CAST(sale AS SIGNED) - CAST(purchase AS SIGNED)'));

        return [$periodOfThisMonthOrdersProfit, $periodOfPreviousMonthOrdersProfit]; // چونکه ستون sale و purchase از نوع دسیمال و بدون علامت هست و ممکن است نتیجه منفی شود برای جلوگیری از این مشکل باید ابتدا ستون را cast کنیم
    }

    /**
     * @param Carbon $thirtyDaysAgo
     * @param Carbon $now
     * @return Collection
     */
    private function getThirtyDaysAgoUsersCount(Carbon $thirtyDaysAgo, Carbon $now): Collection
    {
        $data = User::select('id', 'created_at')
            ->whereBetween('created_at', [$thirtyDaysAgo, $now])
            ->get()
            ->groupBy(function ($date) {
                return Jalalian::fromCarbon($date->created_at)->format('m/d');
            })
            ->map(function ($items, $day) {
                return [
                    'xAxis' => $day,
                    'yAxis' => count($items)
                ];
            });
        return $this->prepareDataByDay($data);
    }

    /**
     * @param Carbon $thirtyDaysAgo
     * @param Carbon $now
     * @return Collection
     */
    private function getThirtyDaysAgoOrdersCount(Carbon $thirtyDaysAgo, Carbon $now): Collection
    {
        $data = Order::acceptedInCalculations()
            ->select('id', 'created_at')
            ->whereBetween('created_at', [$thirtyDaysAgo, $now])
            ->get()
            ->groupBy(function ($date) {
                return Jalalian::fromCarbon($date->created_at)->format('m/d');
            })
            ->map(function ($items, $day) {
                return [
                    'xAxis' => $day,
                    'yAxis' => count($items)
                ];
            });
        return $this->prepareDataByDay($data);
    }

    /**
     * @param Carbon $thirtyDaysAgo
     * @param Carbon $now
     * @return Collection
     */
    private function getThirtyDaysAgoOrdersCosts(Carbon $thirtyDaysAgo, Carbon $now): Collection
    {
        $data = Order::acceptedInCalculations()
            ->whereBetween('created_at', [$thirtyDaysAgo, $now])
            ->get()
            ->groupBy(function ($date) {
                return Jalalian::fromCarbon($date->created_at)->format('m/d');
            })
            ->map(function ($items, $day) {
                return [
                    'xAxis' => $day,
                    'yAxis' => $items->sum('sale')
                ];
            });
        return $this->prepareDataByDay($data);
    }

    /**
     * @param Carbon $thirtyDaysAgo
     * @param Carbon $now
     * @return Collection
     */
    private function getThirtyDaysAgoOrdersProfits(Carbon $thirtyDaysAgo, Carbon $now): Collection
    {
        $data = Order::acceptedInCalculations()
            ->whereBetween('created_at', [$thirtyDaysAgo, $now])
            ->get(['id','registered_melted_gold_order_total_profit','created_at'])
            ->groupBy(function ($date) {
                // Ensure the date is converted to Jalalian format before grouping
                $jalaliDate = Jalalian::fromCarbon($date->created_at);
                return $jalaliDate->format('m/d');
            })
            ->map(function ($items, $day) {
                return [
                    'xAxis' => $day,
                    'yAxis' => $items->sum('registered_melted_gold_order_total_profit')
                ];
            });

        return $this->prepareDataByDay($data);
    }

    /**
     * @return array
     */
    private function getDates(): array
    {
        $now = Jalalian::fromFormat('Y-m-d H:i:s', Jalalian::now())->toCarbon()->endOfDay();
        $thirtyDaysAgo = Jalalian::fromFormat('Y-m-d', Jalalian::now()->subMonths()->format('Y-m-d'))->toCarbon()->endOfDay();
        $beginOfThisMonth = Jalalian::fromFormat('Y-m-d', Jalalian::now()->getFirstDayOfMonth()->format('Y-m-d'))->toCarbon()->startOfDay();
        $beginOfPastMonth = Jalalian::fromFormat('Y-m-d', Jalalian::now()->getLastMonth()->getFirstDayOfMonth()->format('Y-m-d'))->toCarbon()->startOfDay();

        return [$now, $thirtyDaysAgo, $beginOfThisMonth, $beginOfPastMonth];
    }
}
