<?php

use App\Models\Admin;
use App\Models\MetalItem;
use App\Models\MetalItemGroup;
use App\Models\MetalOrder;
use App\Models\MetalOrderCoverageAllocation;
use App\Models\MetalSettlementCase;
use App\Models\MetalTrader;
use App\Models\MetalTraderWallet;
use App\Models\MetalTreasuryLot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Morilog\Jalali\Jalalian;

beforeEach(function () {
    // دیتابیس تست جدا؛ هرگز zarenta_db واقعی را migrate:fresh نمی‌کنیم.
    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql.database', env('DB_TEST_DATABASE', 'zarenta_testing'));
    config()->set('database.connections.mysql.host', env('DB_HOST', '127.0.0.1'));
    config()->set('database.connections.mysql.port', env('DB_PORT', '3306'));
    config()->set('database.connections.mysql.username', env('DB_USERNAME', 'root'));
    config()->set('database.connections.mysql.password', env('DB_PASSWORD', ''));
    DB::purge('mysql');
    DB::reconnect('mysql');

    try {
        DB::connection()->getPdo();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Database driver unavailable: ' . $e->getMessage());
    }

    static $migrated = false;
    if (! $migrated) {
        try {
            // بعضی مایگریشن‌ها به admin ازپیش‌ساخته وابسته‌اند؛ پس از خطای میانی admin می‌سازیم و migrate را ادامه می‌دهیم.
            Artisan::call('migrate:fresh', ['--force' => true]);
        } catch (\Throwable $e) {
            // continue below
        }

        if (Schema::hasTable('admins') && Admin::query()->count() === 0) {
            Admin::query()->create([
                'full_name' => 'stats-admin',
                'phone' => 9000000001,
                'national_code' => 1000000001,
                'password' => bcrypt('secret'),
                'status' => 'active',
            ]);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            // residual unrelated migration failures
        }

        $migrated = Schema::hasTable('metal_orders');
        if (! $migrated) {
            test()->markTestSkipped('metal_orders table missing — run migrations on zarenta_testing');
        }
    }
});

function statsAdmin(): Admin
{
    $existing = Admin::query()->first();
    if ($existing) {
        return $existing;
    }

    return Admin::query()->create([
        'full_name' => 'stats-admin',
        'phone' => random_int(9000000000, 9999999999),
        'national_code' => random_int(1000000000, 9999999999),
        'password' => bcrypt('secret'),
        'status' => 'active',
    ]);
}

function statsTrader(): MetalTrader
{
    return MetalTrader::query()->create([
        'name' => 'stats-trader-'.uniqid(),
        'phone' => (int) ('9'.random_int(100000000, 999999999)),
        'status' => 'active',
    ]);
}

function statsItems(): array
{
    $group = MetalItemGroup::query()->first()
        ?? MetalItemGroup::create(['title' => 'stats-group-'.uniqid(), 'sort_order' => 1]);

    $gram = MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $group->id,
        'title' => 'طلای آبشده-'.uniqid(),
        'unit' => 'gram',
        'is_spot' => true,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'sort_order' => 1,
    ]);

    $coin = MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $group->id,
        'title' => 'سکه بانکی-'.uniqid(),
        'unit' => 'count',
        'is_spot' => true,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'sort_order' => 2,
    ]);

    return [$gram, $coin];
}

function statsOrder(
    MetalTrader $trader,
    MetalItem $item,
    array $overrides = []
): MetalOrder {
    return MetalOrder::query()->create(array_merge([
        'tracking_code' => random_int(1000000000, 9999999999),
        'created_id' => $trader->id,
        'created_type' => 'metal_trader',
        'metal_item_id' => $item->id,
        'product_name' => $item->title,
        'qty' => $item->unit === 'count' ? 2 : 10.5,
        'unit' => $item->unit,
        'fee' => 5000000,
        'amount' => $item->unit === 'count' ? 10000000 : 52500000,
        'status' => 'succeed',
        'order_type' => 'buy',
        'settlement_date' => now()->toDateString(),
        'settlement_status' => 'pending',
        'extra_data' => [],
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('requires admin authentication', function () {
    $trader = statsTrader();

    $this->getJson("/api/v1/admin/metal-traders/{$trader->id}/statistics")
        ->assertUnauthorized();
});

it('returns empty statistics for trader without orders', function () {
    Sanctum::actingAs(statsAdmin(), ['*'], 'admin-api');
    $trader = statsTrader();

    $response = $this->getJson("/api/v1/admin/metal-traders/{$trader->id}/statistics?preset=this_month")
        ->assertOk()
        ->json();

    expect($response['summary']['orders_total'])->toBe(0)
        ->and($response['summary']['by_status']['succeed']['count'])->toBe(0)
        ->and($response['summary']['pnl']['net'])->toBe('0')
        ->and($response['by_product'])->toBe([])
        ->and($response['by_metal_group'])->toBe([])
        ->and($response['date_field'])->toBe('created_at')
        ->and($response['meta']['not_implemented'])->toHaveKey('cancelled_orders');
});

it('counts orders by status without merging them', function () {
    Sanctum::actingAs(statsAdmin(), ['*'], 'admin-api');
    $trader = statsTrader();
    [$gram] = statsItems();

    statsOrder($trader, $gram, ['status' => 'succeed', 'settlement_status' => 'pending']);
    $spotSettled = statsOrder($trader, $gram, ['status' => 'succeed', 'settlement_status' => 'settled', 'amount' => 100]);
    statsOrder($trader, $gram, ['status' => 'succeed', 'settlement_status' => 'settled', 'amount' => 50]);
    statsOrder($trader, $gram, ['status' => 'rejected', 'amount' => 1000]);
    statsOrder($trader, $gram, ['status' => 'pending', 'amount' => 2000]);
    statsOrder($trader, $gram, ['status' => 'processing', 'amount' => 3000]);

    MetalSettlementCase::query()->create([
        'metal_trader_id' => $trader->id,
        'commitment_metal_item_id' => $gram->id,
        'spot_metal_item_id' => $gram->id,
        'settlement_date' => now()->toDateString(),
        'status' => 'open',
        'obligation_side' => 'fiat',
        'net_metal' => '0',
        'net_fiat' => '-100',
        'cash_balance_after' => '-100',
        'spot_metal_balance_after' => '0',
    ])->orders()->attach($spotSettled->id);

    $response = $this->getJson("/api/v1/admin/metal-traders/{$trader->id}/statistics?preset=this_month")
        ->assertOk()
        ->json();

    expect($response['summary']['orders_total'])->toBe(6)
        ->and($response['summary']['by_status']['succeed']['count'])->toBe(3)
        ->and($response['summary']['by_status']['rejected']['count'])->toBe(1)
        ->and($response['summary']['by_status']['pending']['count'])->toBe(1)
        ->and($response['summary']['by_status']['processing']['count'])->toBe(1)
        // یک تعهدی pending + یک حاضر settled متصل به پرونده open
        ->and($response['summary']['unsettled_commitments']['count'])->toBe(2)
        ->and($response['summary']['unsettled_commitments']['basis'])->toBe('forward_settlement_pending_or_open_settlement_case_orders')
        ->and($response['summary']['amounts'][0]['total'])->toBe('52500150');
});

it('keeps gram and count product quantities separate', function () {
    Sanctum::actingAs(statsAdmin(), ['*'], 'admin-api');
    $trader = statsTrader();
    [$gram, $coin] = statsItems();

    statsOrder($trader, $gram, ['qty' => 8.5, 'amount' => 100, 'unit' => 'gram']);
    statsOrder($trader, $coin, ['qty' => 12, 'amount' => 200, 'unit' => 'count']);

    $response = $this->getJson("/api/v1/admin/metal-traders/{$trader->id}/statistics?preset=this_month")
        ->assertOk()
        ->json();

    $products = collect($response['by_product']);
    $gramRow = $products->firstWhere('unit', 'gram');
    $coinRow = $products->firstWhere('unit', 'count');

    expect($products)->toHaveCount(2)
        ->and($gramRow['qty'])->toBe('8.500')
        ->and($gramRow['unit_label'])->toBe('گرم')
        ->and($coinRow['qty'])->toBe('12.000')
        ->and($coinRow['unit_label'])->toBe('عدد');

    $allQtys = $products->pluck('qty')->all();
    expect($allQtys)->not->toContain('20.500');
});

it('builds advanced pie breakdown per metal item group without mixing units', function () {
    Sanctum::actingAs(statsAdmin(), ['*'], 'admin-api');
    $trader = statsTrader();

    $meltedGroup = MetalItemGroup::create([
        'title' => 'آبشده-'.uniqid(),
        'sort_order' => 1,
    ]);
    $coinGroup = MetalItemGroup::create([
        'title' => 'سکه-'.uniqid(),
        'sort_order' => 2,
    ]);

    $spot = MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $meltedGroup->id,
        'title' => 'حاضر',
        'unit' => 'gram',
        'is_spot' => true,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'sort_order' => 1,
    ]);
    $t1 = MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $meltedGroup->id,
        'title' => 'فردایی',
        'unit' => 'gram',
        'is_spot' => false,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'sort_order' => 2,
    ]);
    $t2 = MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $meltedGroup->id,
        'title' => 'پس‌فردایی',
        'unit' => 'gram',
        'is_spot' => false,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'sort_order' => 3,
    ]);
    $fullCoin = MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $coinGroup->id,
        'title' => 'تمام سکه',
        'unit' => 'count',
        'is_spot' => true,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'sort_order' => 1,
    ]);
    $halfCoin = MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $coinGroup->id,
        'title' => 'نیم سکه',
        'unit' => 'count',
        'is_spot' => true,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'sort_order' => 2,
    ]);
    $quarterCoin = MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $coinGroup->id,
        'title' => 'ربع سکه',
        'unit' => 'count',
        'is_spot' => true,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'sort_order' => 3,
    ]);

    statsOrder($trader, $spot, ['qty' => 10, 'amount' => 10]);
    statsOrder($trader, $t1, ['qty' => 20, 'amount' => 20]);
    statsOrder($trader, $t2, ['qty' => 70, 'amount' => 70]);
    statsOrder($trader, $fullCoin, ['qty' => 2, 'amount' => 2]);
    statsOrder($trader, $halfCoin, ['qty' => 4, 'amount' => 4]);
    statsOrder($trader, $quarterCoin, ['qty' => 4, 'amount' => 4]);
    statsOrder($trader, $spot, ['status' => 'rejected', 'qty' => 999, 'amount' => 1]);

    $response = $this->getJson("/api/v1/admin/metal-traders/{$trader->id}/statistics?preset=this_month")
        ->assertOk()
        ->json();

    $groups = collect($response['by_metal_group']);
    $melted = $groups->firstWhere('metal_item_group_id', $meltedGroup->id);
    $coins = $groups->firstWhere('metal_item_group_id', $coinGroup->id);

    expect($groups)->toHaveCount(2)
        ->and($melted['unit'])->toBe('gram')
        ->and($melted['unit_label'])->toBe('گرم')
        ->and($melted['qty_total'])->toBe('100.000')
        ->and(collect($melted['items'])->pluck('qty', 'product_name')->all())->toMatchArray([
            'حاضر' => '10.000',
            'فردایی' => '20.000',
            'پس‌فردایی' => '70.000',
        ])
        ->and($coins['unit'])->toBe('count')
        ->and($coins['unit_label'])->toBe('عدد')
        ->and($coins['qty_total'])->toBe('10.000')
        ->and(collect($coins['items'])->pluck('qty', 'product_name')->all())->toMatchArray([
            'تمام سکه' => '2.000',
            'نیم سکه' => '4.000',
            'ربع سکه' => '4.000',
        ]);
});

it('computes realized pnl only for succeed buy coverage allocations', function () {
    Sanctum::actingAs(statsAdmin(), ['*'], 'admin-api');
    $trader = statsTrader();
    [$gram] = statsItems();

    $buy = statsOrder($trader, $gram, [
        'status' => 'succeed',
        'order_type' => 'buy',
        'qty' => 4,
        'fee' => 5200000,
        'amount' => 20800000,
    ]);
    $sell = statsOrder($trader, $gram, [
        'status' => 'succeed',
        'order_type' => 'sell',
        'qty' => 4,
        'fee' => 5200000,
        'amount' => 20800000,
    ]);
    $rejected = statsOrder($trader, $gram, [
        'status' => 'rejected',
        'order_type' => 'buy',
        'qty' => 4,
        'amount' => 20800000,
    ]);

    $lot = MetalTreasuryLot::query()->create([
        'metal_item_id' => $gram->id,
        'maturity_code' => 'T0',
        'source_type' => 'PHYSICAL_PURCHASE',
        'status' => 'OPEN',
        'initial_qty' => '4.000',
        'remaining_qty' => '0.000',
        'unit_cost' => '5000000',
        'purchased_at' => now(),
    ]);

    foreach ([
        [$buy->id, '800000'],
        [$sell->id, '999999'],
        [$rejected->id, '111111'],
    ] as [$orderId, $pnl]) {
        MetalOrderCoverageAllocation::query()->create([
            'metal_order_id' => $orderId,
            'metal_item_id' => $gram->id,
            'source_type' => 'TREASURY',
            'lot_id' => $lot->id,
            'maturity_code' => 'T0',
            'qty' => '4.000',
            'customer_unit_price' => '5200000',
            'coverage_unit_cost' => '5000000',
            'realized_pnl' => $pnl,
            'allocated_at' => now(),
            'idempotency_key' => 'stats-alloc-'.$orderId.'-'.uniqid(),
        ]);
    }

    $response = $this->getJson("/api/v1/admin/metal-traders/{$trader->id}/statistics?preset=this_month")
        ->assertOk()
        ->json();

    expect($response['summary']['pnl']['net'])->toBe('800000')
        ->and($response['summary']['pnl']['profit'])->toBe('800000')
        ->and($response['summary']['pnl']['loss'])->toBe('0')
        ->and($response['summary']['pnl']['eligible_buy_succeed_orders'])->toBe(1);
});

it('groups monthly buckets by jalali created_at month boundaries', function () {
    Sanctum::actingAs(statsAdmin(), ['*'], 'admin-api');
    $trader = statsTrader();
    [$gram] = statsItems();

    $thisMonth = Jalalian::now();
    $lastMonth = $thisMonth->getLastMonth();
    $lastMonthDate = (new Jalalian($lastMonth->getYear(), $lastMonth->getMonth(), 15))->toCarbon()->setTime(12, 0);
    $thisMonthDate = (new Jalalian($thisMonth->getYear(), $thisMonth->getMonth(), 5))->toCarbon()->setTime(12, 0);

    statsOrder($trader, $gram, [
        'created_at' => $lastMonthDate,
        'updated_at' => $lastMonthDate,
        'amount' => 1000,
    ]);
    statsOrder($trader, $gram, [
        'created_at' => $thisMonthDate,
        'updated_at' => $thisMonthDate,
        'amount' => 2000,
    ]);

    $start = (new Jalalian($lastMonth->getYear(), $lastMonth->getMonth(), 1))->format('Y/m/d');
    $end = Jalalian::now()->format('Y/m/d');

    $response = $this->getJson("/api/v1/admin/metal-traders/{$trader->id}/statistics?start={$start}&end={$end}")
        ->assertOk()
        ->json();

    $monthly = collect($response['monthly']);
    $lastKey = (new Jalalian($lastMonth->getYear(), $lastMonth->getMonth(), 1))->format('Y/m');
    $thisKey = (new Jalalian($thisMonth->getYear(), $thisMonth->getMonth(), 1))->format('Y/m');

    expect($monthly->firstWhere('month', $lastKey)['orders_total'])->toBe(1)
        ->and($monthly->firstWhere('month', $thisKey)['orders_total'])->toBe(1)
        ->and($monthly->firstWhere('month', $lastKey)['amounts'][0]['total'])->toBe('1000')
        ->and($monthly->firstWhere('month', $thisKey)['amounts'][0]['total'])->toBe('2000');
});

it('reports current debt and overdue or defaulted settlement cases', function () {
    Sanctum::actingAs(statsAdmin(), ['*'], 'admin-api');
    $trader = statsTrader();
    [$gram] = statsItems();

    MetalTraderWallet::query()->create([
        'metal_trader_id' => $trader->id,
        'metal_item_id' => null,
        'available_balance' => '-150000',
        'blocked_balance' => '0',
        'fiat_balance' => '0',
        'avg_buy_price' => '0',
    ]);

    $order = statsOrder($trader, $gram, [
        'settlement_status' => 'settled',
        'settlement_date' => Carbon::now()->subDays(3)->toDateString(),
    ]);

    MetalSettlementCase::query()->create([
        'metal_trader_id' => $trader->id,
        'commitment_metal_item_id' => $gram->id,
        'spot_metal_item_id' => $gram->id,
        'settlement_date' => Carbon::now()->subDays(3)->toDateString(),
        'status' => 'open',
        'obligation_side' => 'fiat',
        'net_metal' => '0',
        'net_fiat' => '-150000',
        'cash_balance_after' => '-150000',
        'spot_metal_balance_after' => '0',
    ])->orders()->attach($order->id);

    MetalSettlementCase::query()->create([
        'metal_trader_id' => $trader->id,
        'commitment_metal_item_id' => $gram->id,
        'spot_metal_item_id' => $gram->id,
        'settlement_date' => Carbon::now()->toDateString(),
        'status' => 'open',
        'obligation_side' => 'metal',
        'net_metal' => '-1.000',
        'net_fiat' => '0',
        'cash_balance_after' => '0',
        'spot_metal_balance_after' => '-1.000',
    ]);

    MetalSettlementCase::query()->create([
        'metal_trader_id' => $trader->id,
        'commitment_metal_item_id' => $gram->id,
        'spot_metal_item_id' => $gram->id,
        'settlement_date' => Carbon::now()->addDays(5)->toDateString(),
        'status' => 'open',
        'obligation_side' => 'metal',
        'net_metal' => '-2.000',
        'net_fiat' => '0',
        'cash_balance_after' => '0',
        'spot_metal_balance_after' => '-2.000',
    ]);

    MetalSettlementCase::query()->create([
        'metal_trader_id' => $trader->id,
        'commitment_metal_item_id' => $gram->id,
        'spot_metal_item_id' => $gram->id,
        'settlement_date' => Carbon::now()->subDays(10)->toDateString(),
        'status' => 'defaulted',
        'obligation_side' => 'fiat',
        'net_metal' => '0',
        'net_fiat' => '-50000',
        'cash_balance_after' => '-50000',
        'spot_metal_balance_after' => '0',
        'penalty_total' => '15000',
        'defaulted_at' => now(),
    ]);

    $response = $this->getJson("/api/v1/admin/metal-traders/{$trader->id}/statistics?preset=this_month")
        ->assertOk()
        ->json();

    expect($response['obligations']['current_debt']['fiat']['debt_amount'])->toBe('150000')
        ->and($response['obligations']['settlement_cases']['counts']['open_overdue'])->toBe(1)
        ->and($response['obligations']['settlement_cases']['counts']['open_current'])->toBe(1)
        ->and($response['obligations']['settlement_cases']['counts']['open_future'])->toBe(1)
        ->and($response['obligations']['settlement_cases']['counts']['defaulted'])->toBe(1)
        ->and($response['obligations']['settlement_cases']['defaulted'][0]['status'])->toBe('defaulted');
});

it('lists pending forward orders under current and future settlements', function () {
    Sanctum::actingAs(statsAdmin(), ['*'], 'admin-api');
    $trader = statsTrader();
    [$gram] = statsItems();

    statsOrder($trader, $gram, [
        'status' => 'succeed',
        'settlement_status' => 'pending',
        'settlement_date' => Carbon::now()->toDateString(),
        'qty' => 3,
        'amount' => 30,
        'product_name' => 'حاضر-تست',
    ]);
    statsOrder($trader, $gram, [
        'status' => 'succeed',
        'settlement_status' => 'pending',
        'settlement_date' => Carbon::now()->addDay()->toDateString(),
        'qty' => 5,
        'amount' => 50,
        'product_name' => 'فردایی-تست',
    ]);
    statsOrder($trader, $gram, [
        'status' => 'succeed',
        'settlement_status' => 'pending',
        'settlement_date' => Carbon::now()->addDays(2)->toDateString(),
        'qty' => 7,
        'amount' => 70,
        'product_name' => 'پس‌فردایی-تست',
    ]);
    statsOrder($trader, $gram, [
        'status' => 'succeed',
        'settlement_status' => 'settled',
        'settlement_date' => Carbon::now()->addDays(3)->toDateString(),
        'qty' => 9,
        'amount' => 90,
        'product_name' => 'تسویه‌شده-نباید-بیاد',
    ]);

    $response = $this->getJson("/api/v1/admin/metal-traders/{$trader->id}/statistics?preset=this_month")
        ->assertOk()
        ->json();

    $current = collect($response['obligations']['settlement_cases']['open_current']);
    $future = collect($response['obligations']['settlement_cases']['open_future']);

    expect($response['obligations']['settlement_cases']['counts']['open_current'])->toBe(1)
        ->and($response['obligations']['settlement_cases']['counts']['open_future'])->toBe(2)
        ->and($current->pluck('commitment_metal_item.title')->all())->toContain('حاضر-تست')
        ->and($future->pluck('commitment_metal_item.title')->all())->toMatchArray([
            'فردایی-تست',
            'پس‌فردایی-تست',
        ])
        ->and($future->every(fn ($row) => $row['kind'] === 'pending_order' && $row['status_label'] === 'در انتظار تسویه'))->toBeTrue();
});

it('filters by order type and status', function () {
    Sanctum::actingAs(statsAdmin(), ['*'], 'admin-api');
    $trader = statsTrader();
    [$gram] = statsItems();

    statsOrder($trader, $gram, ['order_type' => 'buy', 'status' => 'succeed']);
    statsOrder($trader, $gram, ['order_type' => 'sell', 'status' => 'succeed']);
    statsOrder($trader, $gram, ['order_type' => 'buy', 'status' => 'rejected']);

    $response = $this->getJson("/api/v1/admin/metal-traders/{$trader->id}/statistics?preset=this_month&order_type=buy&status=succeed")
        ->assertOk()
        ->json();

    expect($response['summary']['orders_total'])->toBe(1)
        ->and($response['filters']['order_type'])->toBe('buy')
        ->and($response['filters']['status'])->toBe('succeed');
});

it('rejects invalid date range', function () {
    Sanctum::actingAs(statsAdmin(), ['*'], 'admin-api');
    $trader = statsTrader();

    $this->getJson("/api/v1/admin/metal-traders/{$trader->id}/statistics?start=1404/10/01&end=1404/01/01")
        ->assertStatus(422);
});

it('aggregates overall statistics across all traders', function () {
    Sanctum::actingAs(statsAdmin(), ['*'], 'admin-api');
    $traderA = statsTrader();
    $traderB = statsTrader();
    [$gram] = statsItems();

    statsOrder($traderA, $gram, ['status' => 'succeed', 'qty' => 5, 'amount' => 50]);
    statsOrder($traderB, $gram, ['status' => 'succeed', 'qty' => 7, 'amount' => 70]);
    statsOrder($traderB, $gram, ['status' => 'rejected', 'qty' => 1, 'amount' => 10]);

    $response = $this->getJson('/api/v1/admin/metal-traders/statistics?preset=this_month')
        ->assertOk()
        ->json();

    expect($response['scope'])->toBe('all_traders')
        ->and($response['trader']['id'])->toBeNull()
        ->and($response['trader']['name'])->toBe('همه تریدرها')
        ->and($response['summary']['orders_total'])->toBe(3)
        ->and($response['summary']['by_status']['succeed']['count'])->toBe(2)
        ->and($response['summary']['by_status']['rejected']['count'])->toBe(1)
        ->and($response['summary']['amounts'][0]['total'])->toBe('120')
        ->and($response['by_metal_group'][0]['qty_total'])->toBe('12.000');
});
