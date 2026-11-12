<?php

namespace App\Services\Metal;

use App\Constants\MetalSettlementCaseStatus;
use App\Models\MetalItem;
use App\Models\MetalOrder;
use App\Models\MetalOrderCoverageAllocation;
use App\Models\MetalSettlementCase;
use App\Models\MetalTrader;
use App\Models\MetalTraderWallet;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

class MetalTraderStatisticsService
{
    public const FIAT_SCALE = 0;

    public const QTY_SCALE = 3;

    public const CURRENCY = 'toman';

    public const CURRENCY_LABEL = 'تومان';

    public const DATE_FIELD = 'created_at';

    /**
     * @param  array{
     *     start: Carbon,
     *     end: Carbon,
     *     metal_item_id?: int|null,
     *     order_type?: string|null,
     *     status?: string|null
     * }  $filters
     */
    public function build(?MetalTrader $trader, array $filters): array
    {
        $start = $filters['start'];
        $end = $filters['end'];
        $metalItemId = $filters['metal_item_id'] ?? null;
        $orderType = $filters['order_type'] ?? null;
        $status = $filters['status'] ?? null;
        $traderId = $trader?->id;

        $ordersQuery = $this->baseOrdersQuery($traderId, $start, $end, $metalItemId, $orderType, $status);
        $orders = (clone $ordersQuery)
            ->get([
                'id',
                'metal_item_id',
                'product_name',
                'qty',
                'unit',
                'amount',
                'fee',
                'status',
                'order_type',
                'created_at',
                'settlement_date',
                'settlement_status',
            ]);

        $orderIds = $orders->pluck('id')->all();
        $pnlByOrder = $this->realizedPnlByOrderIds($orderIds);
        $openCaseOrderIds = $this->openSettlementCaseOrderIds($traderId);

        $summary = $this->buildSummary($orders, $pnlByOrder, $openCaseOrderIds);
        $byProduct = $this->buildByProduct($orders, $pnlByOrder);
        $byMetalGroup = $this->buildByMetalGroup($orders);
        $ordersTrend = $this->buildOrdersTrend($orders, $start, $end);
        $monthly = $this->buildMonthly($orders, $pnlByOrder, $start, $end);
        $obligations = $this->buildObligations($traderId);

        return [
            'scope' => $trader ? 'single_trader' : 'all_traders',
            'trader' => $trader ? [
                'id' => $trader->id,
                'name' => $trader->name,
            ] : [
                'id' => null,
                'name' => 'همه تریدرها',
            ],
            'date_range' => [
                'start' => Jalalian::fromCarbon($start)->format('Y/m/d'),
                'end' => Jalalian::fromCarbon($end)->format('Y/m/d'),
                'start_gregorian' => $start->toDateString(),
                'end_gregorian' => $end->toDateString(),
            ],
            'date_field' => self::DATE_FIELD,
            'filters' => [
                'metal_item_id' => $metalItemId,
                'order_type' => $orderType,
                'status' => $status,
            ],
            'summary' => $summary,
            'by_product' => $byProduct,
            'by_metal_group' => $byMetalGroup,
            'orders_trend' => $ordersTrend,
            'monthly' => $monthly,
            'obligations' => $obligations,
            'meta' => [
                'currency' => self::CURRENCY,
                'currency_label' => self::CURRENCY_LABEL,
                'pnl_basis' => 'coverage_realized_pnl_buy_succeed',
                'pnl_description' => 'سود/زیان قطعی فقط از تخصیص‌های پوشش فعال (reversed_at خالی) برای سفارش‌های خرید succeed محاسبه می‌شود؛ مبلغ سفارش سود نیست.',
                'not_implemented' => [
                    'cancelled_orders' => 'وضعیت لغوشده برای سفارش فلزی در سامانه تعریف نشده است (وضعیت‌ها: pending, processing, succeed, rejected).',
                    'sell_order_realized_pnl' => 'سود/زیان قطعی برای سفارش فروش در منطق فعلی ادمین تعریف نشده و محاسبه نمی‌شود.',
                    'unrealized_pnl' => 'سود/زیان تحقق‌نیافته به قیمت لحظه‌ای وابسته است و در آمار بازه‌ای تریدر گنجانده نشده است.',
                    'commitment_breach_from_delay' => 'نقض تعهد فقط با status=defaulted در metal_settlement_cases محاسبه می‌شود؛ تأخیر یا رد سفارش به‌تنهایی نکول نیست.',
                    'cross_unit_qty_totals' => 'جمع مقدار محصولات با واحدهای متفاوت (مثلاً گرم و عدد) انجام نمی‌شود.',
                ],
            ],
        ];
    }

    private function baseOrdersQuery(
        ?int $traderId,
        Carbon $start,
        Carbon $end,
        ?int $metalItemId,
        ?string $orderType,
        ?string $status
    ) {
        return MetalOrder::query()
            ->where('created_type', 'metal_trader')
            ->when($traderId, fn ($q) => $q->where('created_id', $traderId))
            ->whereBetween(self::DATE_FIELD, [$start, $end])
            ->when($metalItemId, fn ($q) => $q->where('metal_item_id', $metalItemId))
            ->when($orderType, fn ($q) => $q->where('order_type', $orderType))
            ->when($status, fn ($q) => $q->where('status', $status));
    }

    /**
     * @param  list<int>  $orderIds
     * @return array<int, string>
     */
    private function realizedPnlByOrderIds(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $rows = MetalOrderCoverageAllocation::query()
            ->whereNull('reversed_at')
            ->whereIn('metal_order_id', $orderIds)
            ->select('metal_order_id', DB::raw('SUM(realized_pnl) as realized_pnl_sum'))
            ->groupBy('metal_order_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->metal_order_id] = $this->money((string) $row->realized_pnl_sum);
        }

        return $map;
    }

    /**
     * @return list<int>
     */
    private function openSettlementCaseOrderIds(?int $traderId): array
    {
        return MetalSettlementCase::query()
            ->when($traderId, fn ($q) => $q->where('metal_trader_id', $traderId))
            ->where('status', MetalSettlementCaseStatus::OPEN)
            ->with(['orders:id'])
            ->get()
            ->flatMap(fn (MetalSettlementCase $case) => $case->orders->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, MetalOrder>  $orders
     * @param  array<int, string>  $pnlByOrder
     * @param  list<int>  $openCaseOrderIds
     */
    private function buildSummary(Collection $orders, array $pnlByOrder, array $openCaseOrderIds = []): array
    {
        $total = $orders->count();
        $statuses = ['pending', 'processing', 'succeed', 'rejected'];
        $byStatus = [];

        foreach ($statuses as $status) {
            $count = $orders->where('status', $status)->count();
            $byStatus[$status] = [
                'count' => $count,
                'percent' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
                'label' => $this->statusLabel($status),
            ];
        }

        $succeedOrders = $orders->where('status', 'succeed');
        $amountSucceed = '0';
        foreach ($succeedOrders as $order) {
            $amountSucceed = bcadd($amountSucceed, $this->money((string) ($order->amount ?? 0)), self::FIAT_SCALE);
        }

        // تعهد ناتمام:
        // 1) سفارش تعهدی تأییدشده با settlement_status=pending (هنوز به حاضر تسویه نشده)
        // 2) سفارش متصل به پرونده سررسید open (معمولاً حاضر / پس از سررسید با کیف منفی)
        $openCaseOrderIdSet = array_fill_keys($openCaseOrderIds, true);
        $unsettledOrderIds = [];

        foreach ($orders as $order) {
            $orderId = (int) $order->id;
            $isForwardPending = $order->status === 'succeed'
                && ($order->settlement_status ?? null) === 'pending';
            $isOpenCaseOrder = isset($openCaseOrderIdSet[$orderId]);

            if ($isForwardPending || $isOpenCaseOrder) {
                $unsettledOrderIds[$orderId] = true;
            }
        }

        $unsettledCommitments = count($unsettledOrderIds);

        $pnl = $this->aggregatePnl($orders, $pnlByOrder);

        return [
            'orders_total' => $total,
            'by_status' => $byStatus,
            'unsettled_commitments' => [
                'count' => $unsettledCommitments,
                'percent' => $total > 0 ? round(($unsettledCommitments / $total) * 100, 2) : 0,
                'label' => 'تعهدات ناتمام',
                'basis' => 'forward_settlement_pending_or_open_settlement_case_orders',
            ],
            'amounts' => [
                [
                    'currency' => self::CURRENCY,
                    'currency_label' => self::CURRENCY_LABEL,
                    'scope' => 'succeed_orders',
                    'total' => $amountSucceed,
                ],
            ],
            'pnl' => $pnl,
        ];
    }

    /**
     * @param  Collection<int, MetalOrder>  $orders
     * @param  array<int, string>  $pnlByOrder
     */
    private function buildByProduct(Collection $orders, array $pnlByOrder): array
    {
        $grouped = $orders->groupBy(function (MetalOrder $order) {
            $unit = (string) ($order->unit ?: 'gram');

            return implode('|', [
                (string) ($order->metal_item_id ?? 0),
                $unit,
                (string) ($order->order_type ?? ''),
            ]);
        });

        return $grouped->map(function (Collection $group) use ($pnlByOrder) {
            /** @var MetalOrder $first */
            $first = $group->first();
            $unit = (string) ($first->unit ?: 'gram');
            $qty = '0';
            $amount = '0';

            foreach ($group as $order) {
                if ($order->status === 'succeed') {
                    $qty = bcadd($qty, $this->qty((string) ($order->qty ?? 0)), self::QTY_SCALE);
                    $amount = bcadd($amount, $this->money((string) ($order->amount ?? 0)), self::FIAT_SCALE);
                }
            }

            $pnl = $this->aggregatePnl($group, $pnlByOrder);

            return [
                'metal_item_id' => $first->metal_item_id,
                'product_name' => $first->product_name,
                'order_type' => $first->order_type,
                'order_type_label' => $first->order_type === 'sell' ? 'فروش' : 'خرید',
                'unit' => $unit,
                'unit_label' => $this->unitLabel($unit),
                'orders_count' => $group->count(),
                'orders_by_status' => [
                    'pending' => $group->where('status', 'pending')->count(),
                    'processing' => $group->where('status', 'processing')->count(),
                    'succeed' => $group->where('status', 'succeed')->count(),
                    'rejected' => $group->where('status', 'rejected')->count(),
                ],
                'qty' => $qty,
                'qty_scope' => 'succeed_orders',
                'amount' => $amount,
                'amount_scope' => 'succeed_orders',
                'currency' => self::CURRENCY,
                'currency_label' => self::CURRENCY_LABEL,
                'pnl' => $pnl,
            ];
        })->values()->all();
    }

    /**
     * تفکیک مقدار تأییدشده هر گروه فلز به آیتم‌های همان گروه
     * (مثلاً آبشده: حاضر/فردایی/پس‌فردایی؛ سکه: تمام/نیم/ربع).
     * واحدهای متفاوت داخل یک گروه با هم جمع نمی‌شوند.
     *
     * @param  Collection<int, MetalOrder>  $orders
     */
    private function buildByMetalGroup(Collection $orders): array
    {
        $succeedOrders = $orders
            ->where('status', 'succeed')
            ->filter(fn (MetalOrder $order) => (int) ($order->metal_item_id ?? 0) > 0)
            ->values();

        if ($succeedOrders->isEmpty()) {
            return [];
        }

        $itemIds = $succeedOrders->pluck('metal_item_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $items = MetalItem::withoutGlobalScope('visible')
            ->with(['group:id,title,sort_order'])
            ->whereIn('id', $itemIds)
            ->get(['id', 'title', 'unit', 'metal_item_group_id', 'sort_order'])
            ->keyBy('id');

        /** @var array<string, array<string, mixed>> $buckets */
        $buckets = [];

        foreach ($succeedOrders as $order) {
            $metalItemId = (int) $order->metal_item_id;
            $item = $items->get($metalItemId);
            $groupId = (int) ($item?->metal_item_group_id ?? 0);
            $unit = (string) ($order->unit ?: ($item?->unit ?: 'gram'));
            $bucketKey = $groupId.'|'.$unit;

            if (! isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'metal_item_group_id' => $groupId > 0 ? $groupId : null,
                    'group_title' => $item?->group?->title ?: 'بدون گروه',
                    'group_sort_order' => (int) ($item?->group?->sort_order ?? 9999),
                    'unit' => $unit,
                    'unit_label' => $this->unitLabel($unit),
                    'qty_total' => '0',
                    'slices' => [],
                ];
            }

            if (! isset($buckets[$bucketKey]['slices'][$metalItemId])) {
                $buckets[$bucketKey]['slices'][$metalItemId] = [
                    'metal_item_id' => $metalItemId,
                    'product_name' => (string) ($order->product_name ?: ($item?->title ?? 'نامشخص')),
                    'sort_order' => (int) ($item?->sort_order ?? 0),
                    'qty' => '0',
                ];
            }

            $qty = $this->qty((string) ($order->qty ?? 0));
            $buckets[$bucketKey]['qty_total'] = bcadd($buckets[$bucketKey]['qty_total'], $qty, self::QTY_SCALE);
            $buckets[$bucketKey]['slices'][$metalItemId]['qty'] = bcadd(
                $buckets[$bucketKey]['slices'][$metalItemId]['qty'],
                $qty,
                self::QTY_SCALE
            );
        }

        $result = [];
        foreach ($buckets as $bucket) {
            $total = (string) $bucket['qty_total'];
            if (bccomp($total, '0', self::QTY_SCALE) !== 1) {
                continue;
            }

            $slices = collect($bucket['slices'])
                ->sortBy([
                    ['sort_order', 'asc'],
                    ['product_name', 'asc'],
                ])
                ->values()
                ->map(function (array $slice) use ($total) {
                    $qtyFloat = (float) $slice['qty'];
                    $totalFloat = (float) $total;

                    return [
                        'metal_item_id' => $slice['metal_item_id'],
                        'product_name' => $slice['product_name'],
                        'qty' => $slice['qty'],
                        'percent' => $totalFloat > 0 ? round(($qtyFloat / $totalFloat) * 100, 2) : 0,
                    ];
                })
                ->all();

            $result[] = [
                'metal_item_group_id' => $bucket['metal_item_group_id'],
                'group_title' => $bucket['group_title'],
                'group_sort_order' => $bucket['group_sort_order'],
                'unit' => $bucket['unit'],
                'unit_label' => $bucket['unit_label'],
                'qty_total' => $total,
                'qty_scope' => 'succeed_orders',
                'items' => $slices,
            ];
        }

        usort($result, function (array $a, array $b) {
            $sortCmp = ($a['group_sort_order'] ?? 9999) <=> ($b['group_sort_order'] ?? 9999);
            if ($sortCmp !== 0) {
                return $sortCmp;
            }

            return strcmp((string) $a['group_title'], (string) $b['group_title']);
        });

        return array_map(function (array $row) {
            unset($row['group_sort_order']);

            return $row;
        }, $result);
    }

    /**
     * @param  Collection<int, MetalOrder>  $orders
     */
    private function buildOrdersTrend(Collection $orders, Carbon $start, Carbon $end): array
    {
        $days = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        $granularity = $days <= 31 ? 'day' : ($days <= 93 ? 'week' : 'month');

        $buckets = $this->buildBuckets($start, $end, $granularity);
        // فقط وضعیت‌های نهایی؛ pending چندثانیه‌ای است و processing حالت موقت تبادل/شریک است.
        $statuses = ['succeed', 'rejected'];

        $counts = [];
        foreach ($buckets as $key => $meta) {
            foreach ($statuses as $status) {
                $counts[$key][$status] = 0;
            }
        }

        foreach ($orders as $order) {
            $key = $this->bucketKey(Carbon::parse($order->created_at), $granularity);
            if (! isset($counts[$key])) {
                continue;
            }
            $status = (string) $order->status;
            if (! isset($counts[$key][$status])) {
                continue;
            }
            $counts[$key][$status]++;
        }

        $categories = [];
        $series = [];
        foreach ($statuses as $status) {
            $series[$status] = [
                'status' => $status,
                'label' => $this->statusLabel($status),
                'data' => [],
            ];
        }

        foreach ($buckets as $key => $meta) {
            $categories[] = $meta['label'];
            foreach ($statuses as $status) {
                $series[$status]['data'][] = $counts[$key][$status] ?? 0;
            }
        }

        return [
            'granularity' => $granularity,
            'unit_label' => 'سفارش',
            'categories' => $categories,
            'series' => array_values($series),
        ];
    }

    /**
     * @param  Collection<int, MetalOrder>  $orders
     * @param  array<int, string>  $pnlByOrder
     */
    private function buildMonthly(Collection $orders, array $pnlByOrder, Carbon $start, Carbon $end): array
    {
        $months = $this->buildJalaliMonthAxis($start, $end);
        $byMonth = $orders->groupBy(fn (MetalOrder $order) => Jalalian::fromCarbon(Carbon::parse($order->created_at))->format('Y/m'));

        return $months->map(function (array $monthMeta) use ($byMonth, $pnlByOrder) {
            $key = $monthMeta['key'];
            /** @var Collection<int, MetalOrder> $monthOrders */
            $monthOrders = $byMonth->get($key, collect());

            $byStatus = [
                'pending' => $monthOrders->where('status', 'pending')->count(),
                'processing' => $monthOrders->where('status', 'processing')->count(),
                'succeed' => $monthOrders->where('status', 'succeed')->count(),
                'rejected' => $monthOrders->where('status', 'rejected')->count(),
            ];

            $amount = '0';
            foreach ($monthOrders->where('status', 'succeed') as $order) {
                $amount = bcadd($amount, $this->money((string) ($order->amount ?? 0)), self::FIAT_SCALE);
            }

            $qtyByProduct = $monthOrders
                ->where('status', 'succeed')
                ->groupBy(fn (MetalOrder $order) => ($order->metal_item_id ?? 0).'|'.($order->unit ?: 'gram'))
                ->map(function (Collection $group) {
                    /** @var MetalOrder $first */
                    $first = $group->first();
                    $unit = (string) ($first->unit ?: 'gram');
                    $qty = '0';
                    foreach ($group as $order) {
                        $qty = bcadd($qty, $this->qty((string) ($order->qty ?? 0)), self::QTY_SCALE);
                    }

                    return [
                        'metal_item_id' => $first->metal_item_id,
                        'product_name' => $first->product_name,
                        'unit' => $unit,
                        'unit_label' => $this->unitLabel($unit),
                        'qty' => $qty,
                    ];
                })
                ->values()
                ->all();

            return [
                'month' => $key,
                'month_label' => $monthMeta['label'],
                'orders_total' => $monthOrders->count(),
                'orders_by_status' => $byStatus,
                'amounts' => [
                    [
                        'currency' => self::CURRENCY,
                        'currency_label' => self::CURRENCY_LABEL,
                        'scope' => 'succeed_orders',
                        'total' => $amount,
                    ],
                ],
                'qty_by_product' => $qtyByProduct,
                'pnl' => $this->aggregatePnl($monthOrders, $pnlByOrder),
            ];
        })->values()->all();
    }

    private function buildObligations(?int $traderId): array
    {
        $wallets = MetalTraderWallet::query()
            ->when($traderId, fn ($q) => $q->where('metal_trader_id', $traderId))
            ->with(['metalItem' => function ($query) {
                $query->withoutGlobalScopes()->select(['id', 'title', 'unit']);
            }])
            ->get();

        $fiatDebtAmount = '0';
        $fiatAvailable = '0';
        $hasFiatDebt = false;
        /** @var array<int, array<string, mixed>> $metalDebtMap */
        $metalDebtMap = [];

        foreach ($wallets as $wallet) {
            $available = $this->decimalString((string) ($wallet->available_balance ?? 0), $wallet->metal_item_id === null ? self::FIAT_SCALE : self::QTY_SCALE);
            if (bccomp($available, '0', $wallet->metal_item_id === null ? self::FIAT_SCALE : self::QTY_SCALE) >= 0) {
                continue;
            }

            if ($wallet->metal_item_id === null) {
                $hasFiatDebt = true;
                $fiatAvailable = bcadd($fiatAvailable, $available, self::FIAT_SCALE);
                $fiatDebtAmount = bcadd($fiatDebtAmount, bcmul($available, '-1', self::FIAT_SCALE), self::FIAT_SCALE);
                continue;
            }

            $metalItemId = (int) $wallet->metal_item_id;
            $unit = (string) ($wallet->metalItem?->unit ?: 'gram');
            $debtQty = bcmul($available, '-1', self::QTY_SCALE);

            if (! isset($metalDebtMap[$metalItemId])) {
                $metalDebtMap[$metalItemId] = [
                    'metal_item_id' => $metalItemId,
                    'product_name' => $wallet->metalItem?->title,
                    'available_balance' => $available,
                    'debt_qty' => $debtQty,
                    'unit' => $unit,
                    'unit_label' => $this->unitLabel($unit),
                ];
            } else {
                $metalDebtMap[$metalItemId]['available_balance'] = bcadd(
                    $metalDebtMap[$metalItemId]['available_balance'],
                    $available,
                    self::QTY_SCALE
                );
                $metalDebtMap[$metalItemId]['debt_qty'] = bcadd(
                    $metalDebtMap[$metalItemId]['debt_qty'],
                    $debtQty,
                    self::QTY_SCALE
                );
            }
        }

        $fiatDebt = $hasFiatDebt ? [
            'available_balance' => $fiatAvailable,
            'debt_amount' => $fiatDebtAmount,
            'currency' => self::CURRENCY,
            'currency_label' => self::CURRENCY_LABEL,
        ] : null;
        $metalDebts = array_values($metalDebtMap);

        $today = Carbon::now()->toDateString();
        $cases = MetalSettlementCase::query()
            ->when($traderId, fn ($q) => $q->where('metal_trader_id', $traderId))
            ->whereIn('status', [
                MetalSettlementCaseStatus::OPEN,
                MetalSettlementCaseStatus::DEFAULTED,
            ])
            ->with([
                'trader:id,name',
                'commitmentMetalItem' => fn ($q) => $q->withoutGlobalScopes()->select(['id', 'title', 'unit']),
                'spotMetalItem' => fn ($q) => $q->withoutGlobalScopes()->select(['id', 'title', 'unit']),
                'orders:id,tracking_code,product_name,status,order_type',
            ])
            ->orderBy('settlement_date')
            ->get();

        $mapCase = function (MetalSettlementCase $case) {
            return [
                'id' => $case->id,
                'kind' => 'settlement_case',
                'display_id' => (string) $case->id,
                'metal_trader' => $case->trader ? [
                    'id' => $case->trader->id,
                    'name' => $case->trader->name,
                ] : null,
                'status' => $case->status,
                'status_label' => $this->settlementStatusLabel((string) $case->status),
                'settlement_date' => optional($case->settlement_date)?->format('Y-m-d'),
                'obligation_side' => $case->obligation_side,
                'net_fiat' => $this->money((string) ($case->net_fiat ?? 0)),
                'net_metal' => $this->qty((string) ($case->net_metal ?? 0)),
                'currency_label' => self::CURRENCY_LABEL,
                'commitment_metal_item' => $case->commitmentMetalItem ? [
                    'id' => $case->commitmentMetalItem->id,
                    'title' => $case->commitmentMetalItem->title,
                    'unit' => $case->commitmentMetalItem->unit,
                    'unit_label' => $this->unitLabel((string) $case->commitmentMetalItem->unit),
                ] : null,
                'spot_metal_item' => $case->spotMetalItem ? [
                    'id' => $case->spotMetalItem->id,
                    'title' => $case->spotMetalItem->title,
                    'unit' => $case->spotMetalItem->unit,
                    'unit_label' => $this->unitLabel((string) $case->spotMetalItem->unit),
                ] : null,
                'penalty_total' => $case->penalty_total !== null ? $this->money((string) $case->penalty_total) : null,
                'orders' => $case->orders->map(fn (MetalOrder $order) => [
                    'id' => $order->id,
                    'tracking_code' => $order->tracking_code,
                    'product_name' => $order->product_name,
                    'status' => $order->status,
                    'order_type' => $order->order_type,
                ])->values()->all(),
                'detail_path' => '/accounting/settlement-cases/'.$case->id,
            ];
        };

        $open = $cases->where('status', MetalSettlementCaseStatus::OPEN);
        $defaulted = $cases->where('status', MetalSettlementCaseStatus::DEFAULTED);

        $currentCases = $open->filter(function (MetalSettlementCase $case) use ($today) {
            $date = optional($case->settlement_date)?->format('Y-m-d');

            return $date !== null && $date === $today;
        })->values();

        $futureCases = $open->filter(function (MetalSettlementCase $case) use ($today) {
            $date = optional($case->settlement_date)?->format('Y-m-d');

            return $date !== null && $date > $today;
        })->values();

        $overdue = $open->filter(function (MetalSettlementCase $case) use ($today) {
            $date = optional($case->settlement_date)?->format('Y-m-d');

            return $date !== null && $date < $today;
        })->values();

        // سفارش‌های تعهدی تأییدشده‌ی تسویه‌نشده (فردایی/پس‌فردایی و ...)
        // معمولاً تا روز سررسید پرونده settlement_case ندارند؛ باید در جاری/آینده دیده شوند.
        $pendingOrders = MetalOrder::query()
            ->where('created_type', 'metal_trader')
            ->when($traderId, fn ($q) => $q->where('created_id', $traderId))
            ->where('status', 'succeed')
            ->where('settlement_status', 'pending')
            ->whereNotNull('settlement_date')
            ->with([
                'metalItem' => fn ($q) => $q->withoutGlobalScopes()->select(['id', 'title', 'unit']),
            ])
            ->orderBy('settlement_date')
            ->get([
                'id',
                'tracking_code',
                'created_id',
                'metal_item_id',
                'product_name',
                'qty',
                'unit',
                'amount',
                'order_type',
                'settlement_date',
                'settlement_status',
            ]);

        $traderNames = $pendingOrders->isEmpty()
            ? []
            : MetalTrader::query()
                ->whereIn('id', $pendingOrders->pluck('created_id')->unique()->filter()->all())
                ->pluck('name', 'id')
                ->all();

        $mapPendingOrder = function (MetalOrder $order) use ($traderNames) {
            $isBuy = $order->order_type === 'buy';
            $qty = $this->qty((string) ($order->qty ?? 0));
            $amount = $this->money((string) ($order->amount ?? 0));
            $unit = (string) ($order->unit ?: ($order->metalItem?->unit ?: 'gram'));
            $title = (string) ($order->product_name ?: ($order->metalItem?->title ?? 'نامشخص'));
            $createdId = (int) $order->created_id;

            return [
                'id' => $order->id,
                'kind' => 'pending_order',
                'display_id' => (string) ($order->tracking_code ?: $order->id),
                'metal_trader' => $createdId > 0 ? [
                    'id' => $createdId,
                    'name' => $traderNames[$createdId] ?? ('تریدر #'.$createdId),
                ] : null,
                'status' => 'pending_settlement',
                'status_label' => 'در انتظار تسویه',
                'settlement_date' => optional($order->settlement_date)?->format('Y-m-d'),
                'obligation_side' => $isBuy ? 'fiat' : 'metal',
                'net_fiat' => $isBuy
                    ? bcmul($amount, '-1', self::FIAT_SCALE)
                    : $amount,
                'net_metal' => $isBuy
                    ? $qty
                    : bcmul($qty, '-1', self::QTY_SCALE),
                'currency_label' => self::CURRENCY_LABEL,
                'commitment_metal_item' => [
                    'id' => $order->metal_item_id,
                    'title' => $title,
                    'unit' => $unit,
                    'unit_label' => $this->unitLabel($unit),
                ],
                'spot_metal_item' => null,
                'penalty_total' => null,
                'orders' => [[
                    'id' => $order->id,
                    'tracking_code' => $order->tracking_code,
                    'product_name' => $order->product_name,
                    'status' => $order->status,
                    'order_type' => $order->order_type,
                ]],
                'detail_path' => '/orders/metal-orders/'.($order->tracking_code ?: $order->id),
            ];
        };

        $pendingCurrent = $pendingOrders
            ->filter(function (MetalOrder $order) use ($today) {
                $date = optional($order->settlement_date)?->format('Y-m-d');

                return $date !== null && $date === $today;
            })
            ->map($mapPendingOrder)
            ->values();

        $pendingFuture = $pendingOrders
            ->filter(function (MetalOrder $order) use ($today) {
                $date = optional($order->settlement_date)?->format('Y-m-d');

                return $date !== null && $date > $today;
            })
            ->map($mapPendingOrder)
            ->values();

        $currentRows = collect($currentCases->map($mapCase)->all())
            ->concat($pendingCurrent)
            ->sortBy('settlement_date')
            ->values()
            ->all();

        $futureRows = collect($futureCases->map($mapCase)->all())
            ->concat($pendingFuture)
            ->sortBy('settlement_date')
            ->values()
            ->all();

        $overdueFiat = '0';
        foreach ($overdue as $case) {
            if (bccomp((string) ($case->net_fiat ?? 0), '0', self::FIAT_SCALE) < 0) {
                $overdueFiat = bcadd(
                    $overdueFiat,
                    bcmul($this->money((string) $case->net_fiat), '-1', self::FIAT_SCALE),
                    self::FIAT_SCALE
                );
            } elseif (bccomp((string) ($case->cash_balance_after ?? 0), '0', self::FIAT_SCALE) < 0) {
                $overdueFiat = bcadd(
                    $overdueFiat,
                    bcmul($this->money((string) $case->cash_balance_after), '-1', self::FIAT_SCALE),
                    self::FIAT_SCALE
                );
            }
        }

        return [
            'current_debt' => [
                'fiat' => $fiatDebt,
                'metals' => $metalDebts,
            ],
            'overdue_fiat_amount' => [
                'total' => $overdueFiat,
                'currency' => self::CURRENCY,
                'currency_label' => self::CURRENCY_LABEL,
                'basis' => 'open_settlement_cases_with_settlement_date_before_today',
            ],
            'settlement_cases' => [
                'open_current' => $currentRows,
                'open_future' => $futureRows,
                'open_overdue' => $overdue->map($mapCase)->all(),
                'defaulted' => $defaulted->values()->map($mapCase)->all(),
                'counts' => [
                    'open_current' => count($currentRows),
                    'open_future' => count($futureRows),
                    'open_overdue' => $overdue->count(),
                    'defaulted' => $defaulted->count(),
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, MetalOrder>  $orders
     * @param  array<int, string>  $pnlByOrder
     */
    private function aggregatePnl(Collection $orders, array $pnlByOrder): array
    {
        $profit = '0';
        $loss = '0';
        $net = '0';
        $eligibleCount = 0;

        foreach ($orders as $order) {
            if ($order->status !== 'succeed' || $order->order_type !== 'buy') {
                continue;
            }

            $value = $pnlByOrder[(int) $order->id] ?? '0';
            $eligibleCount++;
            $net = bcadd($net, $value, self::FIAT_SCALE);

            if (bccomp($value, '0', self::FIAT_SCALE) >= 0) {
                $profit = bcadd($profit, $value, self::FIAT_SCALE);
            } else {
                $loss = bcadd($loss, bcmul($value, '-1', self::FIAT_SCALE), self::FIAT_SCALE);
            }
        }

        return [
            'profit' => $profit,
            'loss' => $loss,
            'net' => $net,
            'currency' => self::CURRENCY,
            'currency_label' => self::CURRENCY_LABEL,
            'eligible_buy_succeed_orders' => $eligibleCount,
            'basis' => 'coverage_realized_pnl_buy_succeed',
        ];
    }

    /**
     * @return array<string, array{label: string, start: Carbon, end: Carbon}>
     */
    private function buildBuckets(Carbon $start, Carbon $end, string $granularity): array
    {
        $buckets = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor->lte($last)) {
            if ($granularity === 'day') {
                $key = Jalalian::fromCarbon($cursor)->format('Y/m/d');
                $label = Jalalian::fromCarbon($cursor)->format('m/d');
                $bucketStart = $cursor->copy()->startOfDay();
                $bucketEnd = $cursor->copy()->endOfDay();
                $cursor->addDay();
            } elseif ($granularity === 'week') {
                $weekStart = Jalalian::fromCarbon($cursor)->getFirstDayOfWeek();
                $key = $weekStart->format('Y/m/d');
                $label = $weekStart->format('m/d');
                $bucketStart = $weekStart->toCarbon()->startOfDay();
                $bucketEnd = $weekStart->getEndDayOfWeek()->toCarbon()->endOfDay();
                if ($bucketStart->lt($start)) {
                    $bucketStart = $start->copy()->startOfDay();
                }
                if ($bucketEnd->gt($end)) {
                    $bucketEnd = $end->copy()->endOfDay();
                }
                $cursor = $bucketEnd->copy()->addDay()->startOfDay();
            } else {
                $jalali = Jalalian::fromCarbon($cursor);
                $key = $jalali->format('Y/m');
                $label = $jalali->format('F Y');
                $year = $jalali->getYear();
                $month = $jalali->getMonth();
                $daysInMonth = (new Jalalian($year, $month, 1))->getMonthDays();
                $bucketStart = (new Jalalian($year, $month, 1))->toCarbon()->startOfDay();
                $bucketEnd = (new Jalalian($year, $month, $daysInMonth))->toCarbon()->endOfDay();
                if ($bucketEnd->gt($end)) {
                    $bucketEnd = $end->copy()->endOfDay();
                }
                $cursor = $bucketEnd->copy()->addDay()->startOfDay();
            }

            $buckets[$key] = [
                'label' => $label,
                'start' => $bucketStart,
                'end' => $bucketEnd,
            ];
        }

        return $buckets;
    }

    private function bucketKey(Carbon $date, string $granularity): string
    {
        $jalali = Jalalian::fromCarbon($date);

        return match ($granularity) {
            'day' => $jalali->format('Y/m/d'),
            'week' => $jalali->getFirstDayOfWeek()->format('Y/m/d'),
            default => $jalali->format('Y/m'),
        };
    }

    /**
     * @return Collection<int, array{key: string, label: string}>
     */
    private function buildJalaliMonthAxis(Carbon $start, Carbon $end): Collection
    {
        $months = collect();
        $cursor = Jalalian::fromCarbon($start->copy()->startOfDay());
        $last = Jalalian::fromCarbon($end->copy()->startOfDay());

        $year = $cursor->getYear();
        $month = $cursor->getMonth();
        $endYear = $last->getYear();
        $endMonth = $last->getMonth();

        while ($year < $endYear || ($year === $endYear && $month <= $endMonth)) {
            $jalali = new Jalalian($year, $month, 1);
            $months->push([
                'key' => $jalali->format('Y/m'),
                'label' => $jalali->format('F').' '.$jalali->format('Y'),
            ]);

            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }

        return $months;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'در انتظار',
            'processing' => 'در حال پردازش',
            'succeed' => 'تأییدشده',
            'rejected' => 'ردشده',
            default => $status,
        };
    }

    private function settlementStatusLabel(string $status): string
    {
        return match ($status) {
            MetalSettlementCaseStatus::OPEN => 'باز',
            MetalSettlementCaseStatus::FULFILLED => 'تسویه‌شده',
            MetalSettlementCaseStatus::DEFAULTED => 'نکول',
            default => $status,
        };
    }

    private function unitLabel(string $unitId): string
    {
        $unit = Unit::cached($unitId);
        if ($unit?->name) {
            return (string) $unit->name;
        }

        return match ($unitId) {
            'gram' => 'گرم',
            'count' => 'عدد',
            'mazane' => 'مظنه',
            'kilogram' => 'کیلوگرم',
            'ounce' => 'انس',
            'ton' => 'تن',
            default => $unitId,
        };
    }

    private function money(string $value): string
    {
        return $this->decimalString($value, self::FIAT_SCALE);
    }

    private function qty(string $value): string
    {
        return $this->decimalString($value, self::QTY_SCALE);
    }

    private function decimalString(string $value, int $scale): string
    {
        if (! is_numeric($value)) {
            $value = '0';
        }

        return bcadd((string) $value, '0', $scale);
    }
}
