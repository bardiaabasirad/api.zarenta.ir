<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\MetalItem;
use App\Models\MetalItemGroup;
use App\Models\MetalOrder;
use App\Models\MetalOrderCoverageAllocation;
use App\Models\MetalPhysicalDeliveryRequest;
use App\Models\MetalTrader;
use App\Models\MetalTraderBankAccount;
use App\Models\MetalTraderFiatWithdrawal;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;

class DashboardController extends Controller
{
    public function badges()
    {
        return response()->json([
            [
                'type' => 'metal_order',
                'count' => MetalOrder::where('status', 'pending')->count(),
            ],
            [
                'type' => 'bank_account',
                'count' => MetalTraderBankAccount::where('status', 'pending')->count(),
            ],
            [
                'type' => 'fiat_withdrawal',
                'count' => MetalTraderFiatWithdrawal::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function notifications()
    {
        $adminId = auth()->id();

        $notifications = Notification::where('is_active', true)
            ->whereDoesntHave('reads', function ($query) use ($adminId) {
                $query->where('admin_id', $adminId);
            })
            ->select(['id', 'title', 'message', 'created_at'])
            ->get();

        return response()->json($notifications);
    }

    /**
     * Initial dashboard payload: current-month widgets + default chart ranges.
     */
    public function all(Request $request)
    {
        [$start, $end] = $this->resolveDateRange(
            $request->input('start'),
            $request->input('end'),
            30
        );

        return response()->json([
            'widgets' => $this->buildWidgets(0),
            'date_range' => [
                'start' => Jalalian::fromCarbon($start)->format('Y/m/d'),
                'end' => Jalalian::fromCarbon($end)->format('Y/m/d'),
            ],
            'metal_charts' => $this->buildMetalCharts($start, $end),
            'revenue_chart' => $this->buildRevenueChart($start, $end),
        ]);
    }

    public function widgets(Request $request)
    {
        $monthsAgo = (int) $request->input('months_ago', 0);
        if ($monthsAgo < 0) {
            throw ValidationException::withMessages([
                'months_ago' => ['بازه ماه نمی‌تواند در آینده باشد'],
            ]);
        }

        return response()->json([
            'widgets' => $this->buildWidgets($monthsAgo),
        ]);
    }

    public function metalCharts(Request $request)
    {
        [$start, $end] = $this->resolveDateRange(
            $request->input('start'),
            $request->input('end'),
            30
        );

        return response()->json([
            'date_range' => [
                'start' => Jalalian::fromCarbon($start)->format('Y/m/d'),
                'end' => Jalalian::fromCarbon($end)->format('Y/m/d'),
            ],
            'metal_charts' => $this->buildMetalCharts($start, $end),
        ]);
    }

    public function metalGroupChart(Request $request, MetalItemGroup $metalItemGroup)
    {
        [$start, $end] = $this->resolveDateRange(
            $request->input('start'),
            $request->input('end'),
            30
        );

        $chart = $this->buildMetalCharts($start, $end, $metalItemGroup->id)->first();

        return response()->json([
            'date_range' => [
                'start' => Jalalian::fromCarbon($start)->format('Y/m/d'),
                'end' => Jalalian::fromCarbon($end)->format('Y/m/d'),
            ],
            'chart' => $chart,
        ]);
    }

    public function revenueChart(Request $request)
    {
        [$start, $end] = $this->resolveDateRange(
            $request->input('start'),
            $request->input('end'),
            30
        );

        return response()->json([
            'date_range' => [
                'start' => Jalalian::fromCarbon($start)->format('Y/m/d'),
                'end' => Jalalian::fromCarbon($end)->format('Y/m/d'),
            ],
            'revenue_chart' => $this->buildRevenueChart($start, $end),
        ]);
    }

    private function buildWidgets(int $monthsAgo): array
    {
        [$from, $to, $jalaliMonth] = $this->jalaliMonthBounds($monthsAgo);

        return [
            'months_ago' => $monthsAgo,
            'is_current_month' => $monthsAgo === 0,
            'year' => $jalaliMonth->getYear(),
            'month' => $jalaliMonth->getMonth(),
            'month_label' => $jalaliMonth->format('F') . ' ماه ' . $jalaliMonth->format('Y'),
            'revenue' => $this->siteRevenue($from, $to),
            'users_count' => User::completedRegistered()
                ->whereBetween('created_at', [$from, $to])
                ->count(),
            'traders_count' => MetalTrader::query()
                ->whereBetween('created_at', [$from, $to])
                ->count(),
        ];
    }

    private function buildMetalCharts(Carbon $start, Carbon $end, ?int $groupId = null): Collection
    {
        $groupsQuery = MetalItemGroup::query()
            ->with(['metalItems' => function ($query) {
                $query->withoutGlobalScope('visible')
                    ->orderBy('sort_order')->orderBy('id')
                    ->select(['id', 'title', 'unit', 'metal_item_group_id', 'sort_order']);
            }])
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($groupId) {
            $groupsQuery->where('id', $groupId);
        }

        $groups = $groupsQuery->get()->filter(fn (MetalItemGroup $group) => $group->metalItems->isNotEmpty());

        if ($groups->isEmpty()) {
            return collect();
        }

        $metalIds = $groups->flatMap(fn (MetalItemGroup $group) => $group->metalItems->pluck('id'))->unique()->values();

        $orders = MetalOrder::query()
            ->where('status', 'succeed')
            ->whereIn('metal_item_id', $metalIds)
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'metal_item_id', 'qty', 'unit', 'created_at']);

        $days = $this->buildDayAxis($start, $end);
        $ordersByMetalAndDay = $orders
            ->groupBy('metal_item_id')
            ->map(function (Collection $metalOrders) {
                return $metalOrders
                    ->groupBy(fn (MetalOrder $order) => Jalalian::fromCarbon($order->created_at)->format('Y/m/d'))
                    ->map(fn (Collection $dayOrders) => round((float) $dayOrders->sum('qty'), 3));
            });

        return $groups->map(function (MetalItemGroup $group) use ($days, $ordersByMetalAndDay) {
            $unit = $this->resolveGroupUnit($group->metalItems);
            $categories = $days
                ->map(fn (string $day) => Jalalian::fromFormat('Y/m/d', $day)->format('m/d'))
                ->values()
                ->all();

            $series = $group->metalItems->map(function (MetalItem $metal) use ($days, $ordersByMetalAndDay) {
                $byDay = $ordersByMetalAndDay->get($metal->id, collect());

                return [
                    'metal_item_id' => $metal->id,
                    'name' => $metal->title,
                    'data' => $days->map(fn (string $day) => (float) ($byDay[$day] ?? 0))->values()->all(),
                ];
            })->values();

            return [
                'group_id' => $group->id,
                'group_title' => $group->title,
                'unit' => $unit,
                'unit_label' => $unit === 'count' ? 'عدد' : 'گرم',
                'metals' => $group->metalItems->map(fn (MetalItem $metal) => [
                    'id' => $metal->id,
                    'title' => $metal->title,
                    'unit' => $metal->unit,
                ])->values(),
                'categories' => $categories,
                'series' => $series,
            ];
        })->values();
    }

    private function buildRevenueChart(Carbon $start, Carbon $end): array
    {
        $days = $this->buildDayAxis($start, $end);

        $productProfits = Order::acceptedInCalculations()
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'registered_melted_gold_order_total_profit', 'created_at'])
            ->groupBy(fn (Order $order) => Jalalian::fromCarbon($order->created_at)->format('Y/m/d'))
            ->map(fn (Collection $items) => (float) $items->sum('registered_melted_gold_order_total_profit'));

        $realizedPnls = MetalOrderCoverageAllocation::query()
            ->whereNull('reversed_at')
            ->whereBetween('allocated_at', [$start, $end])
            ->get(['id', 'realized_pnl', 'allocated_at'])
            ->groupBy(fn ($row) => Jalalian::fromCarbon($row->allocated_at)->format('Y/m/d'))
            ->map(fn (Collection $items) => (float) $items->sum('realized_pnl'));

        $deliveryFees = MetalPhysicalDeliveryRequest::query()
            ->whereNotNull('fee_recognized_at')
            ->whereNull('fee_refunded_at')
            ->whereBetween('fee_recognized_at', [$start, $end])
            ->get(['id', 'fee_amount', 'fee_recognized_at'])
            ->groupBy(fn ($row) => Jalalian::fromCarbon($row->fee_recognized_at)->format('Y/m/d'))
            ->map(fn (Collection $items) => (float) $items->sum('fee_amount'));

        $points = $days->map(function (string $day) use ($productProfits, $realizedPnls, $deliveryFees) {
            $value = (float) ($productProfits[$day] ?? 0)
                + (float) ($realizedPnls[$day] ?? 0)
                + (float) ($deliveryFees[$day] ?? 0);

            return [
                'xAxis' => Jalalian::fromFormat('Y/m/d', $day)->format('m/d'),
                'date' => $day,
                'yAxis' => round($value),
            ];
        })->values();

        return [
            'unit_label' => 'تومان',
            'points' => $points,
        ];
    }

    private function siteRevenue(Carbon $from, Carbon $to): float
    {
        $productProfit = (float) Order::acceptedInCalculations()
            ->whereBetween('created_at', [$from, $to])
            ->sum('registered_melted_gold_order_total_profit');

        $realizedPnl = (float) MetalOrderCoverageAllocation::query()
            ->whereNull('reversed_at')
            ->whereBetween('allocated_at', [$from, $to])
            ->sum('realized_pnl');

        $deliveryFees = (float) MetalPhysicalDeliveryRequest::query()
            ->whereNotNull('fee_recognized_at')
            ->whereNull('fee_refunded_at')
            ->whereBetween('fee_recognized_at', [$from, $to])
            ->sum('fee_amount');

        return round($productProfit + $realizedPnl + $deliveryFees);
    }

    private function resolveGroupUnit(Collection $metals): string
    {
        $counts = $metals->countBy(fn (MetalItem $metal) => $metal->unit ?: 'gram');

        return $counts->sortDesc()->keys()->first() ?: 'gram';
    }

    private function buildDayAxis(Carbon $start, Carbon $end): Collection
    {
        $days = collect();
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $days->push(Jalalian::fromCarbon($cursor)->format('Y/m/d'));
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Jalalian}
     */
    private function jalaliMonthBounds(int $monthsAgo): array
    {
        // Jalalian::subMonths() requires months >= 1
        $jalaliMonth = $monthsAgo > 0
            ? Jalalian::now()->subMonths($monthsAgo)
            : Jalalian::now();

        $year = $jalaliMonth->getYear();
        $month = $jalaliMonth->getMonth();
        $daysInMonth = $jalaliMonth->getMonthDays();

        $from = (new Jalalian($year, $month, 1))->toCarbon()->startOfDay();

        if ($monthsAgo === 0) {
            $to = Carbon::now()->endOfDay();
        } else {
            $to = (new Jalalian($year, $month, $daysInMonth))->toCarbon()->endOfDay();
        }

        return [$from, $to, new Jalalian($year, $month, 1)];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(?string $start, ?string $end, int $defaultDays): array
    {
        if ($start && $end) {
            try {
                $startCarbon = Jalalian::fromFormat('Y/m/d', $this->normalizeJalaliDate($start))->toCarbon()->startOfDay();
                $endCarbon = Jalalian::fromFormat('Y/m/d', $this->normalizeJalaliDate($end))->toCarbon()->endOfDay();
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'start' => ['بازه تاریخ نامعتبر است'],
                ]);
            }

            if ($startCarbon->gt($endCarbon)) {
                throw ValidationException::withMessages([
                    'start' => ['تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد'],
                ]);
            }

            if ($startCarbon->diffInDays($endCarbon) > 366) {
                throw ValidationException::withMessages([
                    'start' => ['حداکثر بازه مجاز یک سال است'],
                ]);
            }

            return [$startCarbon, $endCarbon];
        }

        $endCarbon = Carbon::now()->endOfDay();
        $startCarbon = Carbon::now()->subDays($defaultDays)->startOfDay();

        return [$startCarbon, $endCarbon];
    }

    private function normalizeJalaliDate(string $value): string
    {
        return str_replace('-', '/', trim($value));
    }
}
