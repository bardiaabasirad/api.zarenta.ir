<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MetalTraderStatisticsRequest;
use App\Http\Requests\MetalTraderStoreRequest;
use App\Http\Requests\MetalTraderUpdateRequest;
use App\Http\Requests\UpdateProductSettingsRequest;
use App\Jobs\SmsJob;
use App\Models\BlockReason;
use App\Models\DealingGroup;
use App\Models\MetalItem;
use App\Models\MetalTrader;
use App\Models\MetalTraderLead;
use App\Models\MetalTraderLog;
use App\Models\MetalTraderWallet;
use App\Models\Setting;
use App\Models\VerificationCode;
use App\Services\Metal\MetalTraderStatisticsService;
use App\Services\MetalTraderService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\Jalalian;

class MetalTraderController extends Controller
{
    public function index()
    {
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');
        $id = request()->input('id');
        $name = request()->input('name');
        $phone = request()->input('phone');
        $status = request()->input('status');
        $dealingGroupId = request()->input('dealing_group_id');
        $lastSeen = request()->input('last_seen');
        $onlineIds = array_values(array_filter(
            array_map('intval', (array) request()->input('online_ids', [])),
            fn (int $value) => $value > 0
        ));

        $metalTraders = MetalTrader::query();

        $metalTraders = $metalTraders->select('id','dealing_group_id', 'name','phone','avatar','last_seen','status')
            ->when(isset($id), function ($query) use ($id){
                $query->where('id', 'like', '%' .$id . '%');
            })
            ->when(isset($name), function ($query) use ($name){
                $query->where('name', 'like', '%' . $name . '%');
            })
            ->when(isset($dealingGroupId), function ($query) use ($dealingGroupId){
                $query->where('dealing_group_id', $dealingGroupId);
            })
            ->when(isset($status) && $status !== 'all', function ($query) use ($status){
                $query->where('status', $status);
            })
            ->when(isset($phone), function ($query) use ($phone){
                $query->where('phone', 'like', '%' . $phone . '%');
            })
            ->when($lastSeen === 'online', function ($query) use ($onlineIds) {
                if ($onlineIds === []) {
                    $query->whereRaw('0 = 1');
                    return;
                }

                $query->whereIn('id', $onlineIds);
            })
            ->when($lastSeen === '3h', function ($query) {
                $query->where('last_seen', '>=', now()->subHours(3));
            })
            ->when($lastSeen === '6h', function ($query) {
                $query->where('last_seen', '>=', now()->subHours(6));
            })
            ->when($lastSeen === '12h', function ($query) {
                $query->where('last_seen', '>=', now()->subHours(12));
            })
            ->when($lastSeen === '1d', function ($query) {
                $query->where('last_seen', '>=', now()->subDay());
            })
            ->when($lastSeen === '2d', function ($query) {
                $query->where('last_seen', '>=', now()->subDays(2));
            })
            ->when($lastSeen === '3d', function ($query) {
                $query->where('last_seen', '>=', now()->subDays(3));
            })
            ->when($lastSeen === '1w', function ($query) {
                $query->where('last_seen', '>=', now()->subWeek());
            })
            ->when($lastSeen === '1m', function ($query) {
                $query->where('last_seen', '>=', now()->subMonth());
            })
            ->when($lastSeen === 'over_1m', function ($query) {
                $query->where(function ($q) {
                    $q->where('last_seen', '<', now()->subMonth())
                        ->orWhereNull('last_seen');
                });
            })
            ->orderBy($sortBy??'created_at', $dir??'desc')
            ->paginate($count??config('app.per_page'));

        return response()->json([
            'metal_traders' => $metalTraders
        ]);
    }

    public function search()
    {
        $q = request()->input('q');

        // 2. کوئری MetalTrader همراه با گروه‌بندی شروط سرچ
        $metalTraders = MetalTrader::query()
            ->select('id', 'name', 'phone')
            ->when($q, function ($query, $q) {
                $query->where(function ($subQuery) use ($q) {
                    $subQuery->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->with('spotFiatWallet')
            ->get();

        return response()->json([
            'metal_traders' => $metalTraders
        ]);
    }

    public function leads()
    {
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');
        $id = request()->input('id');
        $phone = request()->input('phone');
        $lead_type = request()->input('lead_type');

        $metalTraders = MetalTraderLead::query();

        $metalTraders = $metalTraders->select('id','phone','last_attempt_at','lead_type','created_at')
            ->when(isset($id), function ($query) use ($id){
                $query->where('id', 'like', '%' .$id . '%');
            })
            ->when(isset($phone), function ($query) use ($phone){
                $query->where('phone', 'like', '%' . $phone . '%');
            })
            ->when(isset($lead_type) && $lead_type !== 'all', function ($query) use ($lead_type){
                $query->where('lead_type', $lead_type);
            })
            ->orderBy($sortBy??'created_at', $dir??'desc')
            ->paginate($count??config('app.per_page'));

        return response()->json([
            'metal_trader_leads' => $metalTraders
        ]);
    }

    public function create()
    {
        return response()->json([
            'dealing_groups' => DealingGroup::select('id', 'name')->get(),
        ]);
    }

    public function store(MetalTraderStoreRequest $request, MetalTraderService $metalTraderService)
    {
        $attributes = $request->validated();
        $attributes['status'] = 'active';
        $metalTrader = MetalTrader::create($attributes);

        $metalTraderService->notify($metalTrader);

        return response()->json([
            'client' => $metalTrader,
            'message' => 'کلاینت جدید با موفقیت ایجاد شد'
        ]);
    }

    public function update(MetalTraderUpdateRequest $request, MetalTrader $metal_trader, MetalTraderService $service)
    {
        $service->updateWithLogging(
            $metal_trader,
            $request->validated()
        );

        return response()->json([
            'metal_trader' => $metal_trader->fresh()->load(['dealingGroup']),
            'message' => 'اطلاعات کلاینت با موفقیت بروزرسانی شد',
        ]);
    }

    public function updateProductSettings(UpdateProductSettingsRequest $request, MetalTrader $metal_trader)
    {
        // Get the current products_settings as an array
        $currentSettings = $metal_trader->products_settings ?? [];

        // Get only the validated data from the request
        $newSettings = $request->validated();

        // Merge the new settings with the current settings
        // This will update existing keys and add new ones while preserving others
        $updatedSettings = array_merge($currentSettings, $newSettings);

        // Update the client using the custom updateWithLogging method
        $metal_trader->update([
            'products_settings' => $updatedSettings
        ]);

        return response()->json([
            'message' => 'اطلاعات کلاینت با موفقیت بروزرسانی شد',
            'client' => $metal_trader
        ]);
    }

    public function show(MetalTrader $metal_trader)
    {
        $metal_trader->load([
            'assets',
            'dealingGroup:id,name'
        ]);

        $latestVerificationCode = VerificationCode::where('authenticatable_type', 'App\Models\MetalTrader')
            ->where('phone', $metal_trader->phone)
            ->latest()
            ->first();

        if ($latestVerificationCode) {
            $latestVerificationCode->makeVisible('code');
        }

        return response()->json([
            'metal_trader' => $metal_trader,
            'metal_items' => MetalItem::withoutGlobalScope('visible')->get(['id', 'unit']),
            'latest_verification_code' => $latestVerificationCode,
            'dealing_groups' => DealingGroup::select('id', 'name')->get(),
            'block_reasons' => BlockReason::where('type','clients')->get(),
        ]);
    }

    public function assets(MetalTrader $metal_trader)
    {
        $groupedAssets = MetalTraderWallet::query()
            ->where('metal_trader_wallets.metal_trader_id', $metal_trader->id)
            ->leftJoin('metal_items', 'metal_trader_wallets.metal_item_id', '=', 'metal_items.id')
            ->with([
                'metalItem' => function ($query) {
                    $query->withoutGlobalScopes()
                        ->select(['id', 'title', 'unit', 'is_spot', 'metal_item_group_id', 'sort_order']);
                },
            ])
            ->select('metal_trader_wallets.*')
            ->orderByRaw("
                CASE
                    WHEN metal_trader_wallets.metal_item_id IS NULL THEN 1
                    WHEN metal_items.is_spot = 1 THEN 2
                    ELSE 3
                END ASC
            ")
            ->orderBy('metal_items.metal_item_group_id', 'asc')
            ->orderBy('metal_items.sort_order', 'asc')
            ->get()
            ->groupBy(function ($wallet) {
                return ($wallet->metal_item_id === null || $wallet->metalItem?->is_spot)
                    ? 'spot_and_cash'
                    : 'forward_items';
            });

        return response()->json([
            'assets' => [
                'spot_and_cash' => ($groupedAssets->get('spot_and_cash') ?? collect())->values(),
                'forward_items' => ($groupedAssets->get('forward_items') ?? collect())->values(),
            ],
        ]);
    }

    public function overallStatistics(
        MetalTraderStatisticsRequest $request,
        MetalTraderStatisticsService $statisticsService
    ) {
        [$start, $end] = $this->resolveStatisticsDateRange(
            $request->input('preset'),
            $request->input('start'),
            $request->input('end')
        );

        return response()->json(
            $statisticsService->build(null, [
                'start' => $start,
                'end' => $end,
                'metal_item_id' => $request->filled('metal_item_id') ? $request->integer('metal_item_id') : null,
                'order_type' => $request->input('order_type'),
                'status' => $request->input('status'),
            ])
        );
    }

    public function statistics(
        MetalTraderStatisticsRequest $request,
        MetalTrader $metal_trader,
        MetalTraderStatisticsService $statisticsService
    ) {
        [$start, $end] = $this->resolveStatisticsDateRange(
            $request->input('preset'),
            $request->input('start'),
            $request->input('end')
        );

        return response()->json(
            $statisticsService->build($metal_trader, [
                'start' => $start,
                'end' => $end,
                'metal_item_id' => $request->filled('metal_item_id') ? $request->integer('metal_item_id') : null,
                'order_type' => $request->input('order_type'),
                'status' => $request->input('status'),
            ])
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveStatisticsDateRange(?string $preset, ?string $start, ?string $end): array
    {
        $preset = $preset ?: null;

        if (in_array($preset, ['this_month', 'last_month', 'quarter', 'year'], true)) {
            return $this->presetToDateRange($preset);
        }

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

        return $this->presetToDateRange('this_month');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function presetToDateRange(string $preset): array
    {
        $now = Jalalian::now();

        return match ($preset) {
            'last_month' => (function () use ($now) {
                $last = $now->getLastMonth();
                $year = $last->getYear();
                $month = $last->getMonth();
                $from = (new Jalalian($year, $month, 1))->toCarbon()->startOfDay();
                $to = (new Jalalian($year, $month, $last->getMonthDays()))->toCarbon()->endOfDay();

                return [$from, $to];
            })(),
            'quarter' => (function () use ($now) {
                $from = $now->getFirstDayOfQuarter()->toCarbon()->startOfDay();
                $to = Carbon::now()->endOfDay();

                return [$from, $to];
            })(),
            'year' => (function () use ($now) {
                $from = $now->getFirstDayOfYear()->toCarbon()->startOfDay();
                $to = Carbon::now()->endOfDay();

                return [$from, $to];
            })(),
            default => (function () use ($now) {
                $year = $now->getYear();
                $month = $now->getMonth();
                $from = (new Jalalian($year, $month, 1))->toCarbon()->startOfDay();
                $to = Carbon::now()->endOfDay();

                return [$from, $to];
            })(),
        };
    }

    private function normalizeJalaliDate(string $value): string
    {
        return str_replace('-', '/', trim($value));
    }

    public function log($metal_trader_id)
    {
        $logs = MetalTraderLog::where('metal_trader_id', $metal_trader_id)
            ->with(['loggable' => function($query){
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
