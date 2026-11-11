<?php

use App\Models\MetalItem;
use App\Models\MetalItemGroup;
use App\Models\MetalOrder;
use App\Models\MetalSettlementCase;
use App\Models\MetalTrader;
use App\Models\MetalTraderWallet;
use App\Models\WalletTransaction;
use App\Services\AssetService;
use App\Services\SettlementFulfillmentService;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    try {
        DB::connection()->getPdo();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Database driver unavailable: ' . $e->getMessage());
    }

    if (! Schema::hasTable('metal_settlement_cases')) {
        test()->markTestSkipped('metal_settlement_cases table missing — run migrations');
    }
});

function settlementGroup(): MetalItemGroup
{
    return MetalItemGroup::query()->first()
        ?? MetalItemGroup::create(['title' => 'settle-group-' . uniqid(), 'sort_order' => 1]);
}

function settlementTrader(): MetalTrader
{
    return MetalTrader::query()->create([
        'name' => 'settle-trader-' . uniqid(),
        'phone' => (int) ('9' . random_int(100000000, 999999999)),
        'api_key' => MetalTrader::generateApiKey(),
        'status' => 'active',
    ]);
}

/**
 * @return array{0: MetalItem, 1: MetalItem} [spot, forward]
 */
function settlementItems(): array
{
    $group = settlementGroup();
    $spot = MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $group->id,
        'title' => 'spot-' . uniqid(),
        'unit' => 'gram',
        'is_spot' => true,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'settlement_working_days' => 0,
        'sort_order' => 1,
    ]);

    $forward = MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $group->id,
        'title' => 'fwd-' . uniqid(),
        'unit' => 'gram',
        'is_spot' => false,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'settlement_working_days' => 1,
        'settlement_metal_item_id' => $spot->id,
        'sort_order' => 2,
    ]);

    return [$spot, $forward];
}

function seedCash(MetalTrader $trader, string $amount): MetalTraderWallet
{
    return MetalTraderWallet::query()->create([
        'metal_trader_id' => $trader->id,
        'metal_item_id' => null,
        'available_balance' => $amount,
        'locked_balance' => '0',
        'blocked_balance' => '0',
        'fiat_balance' => '0',
        'avg_buy_price' => '0',
    ]);
}

function succeedForwardBuy(MetalTrader $trader, MetalItem $forward, string $qty, string $amount): MetalOrder
{
    $order = MetalOrder::query()->create([
        'tracking_code' => random_int(1000000000, 9999999999),
        'created_id' => $trader->id,
        'created_type' => 'metal_trader',
        'metal_item_id' => $forward->id,
        'order_type' => 'buy',
        'status' => 'succeed',
        'settlement_date' => now()->toDateString(),
        'settlement_status' => 'pending',
        'product_name' => $forward->title,
        'qty' => $qty,
        'unit' => 'gram',
        'fee' => bcdiv($amount, $qty, 0),
        'amount' => $amount,
    ]);

    AssetService::updateAsset($order->fresh());

    return $order->fresh();
}

function succeedForwardSell(MetalTrader $trader, MetalItem $forward, string $qty, string $amount): MetalOrder
{
    $order = MetalOrder::query()->create([
        'tracking_code' => random_int(1000000000, 9999999999),
        'created_id' => $trader->id,
        'created_type' => 'metal_trader',
        'metal_item_id' => $forward->id,
        'order_type' => 'sell',
        'status' => 'succeed',
        'settlement_date' => now()->toDateString(),
        'settlement_status' => 'pending',
        'product_name' => $forward->title,
        'qty' => $qty,
        'unit' => 'gram',
        'fee' => bcdiv($amount, $qty, 0),
        'amount' => $amount,
    ]);

    AssetService::updateAsset($order->fresh());

    return $order->fresh();
}

it('does not open a settlement case when cash covers the buy settlement', function () {
    $trader = settlementTrader();
    [$spot, $forward] = settlementItems();
    seedCash($trader, '25000000');
    succeedForwardBuy($trader, $forward, '1', '25000000');

    SettlementService::settleOrdersForTrader($trader->id, $forward->id, now()->toDateString());

    expect(MetalSettlementCase::query()->where('metal_trader_id', $trader->id)->count())->toBe(0);

    $cash = MetalTraderWallet::query()
        ->where('metal_trader_id', $trader->id)
        ->whereNull('metal_item_id')
        ->first();

    expect((float) $cash->available_balance)->toBe(0.0);
});

it('opens a fiat settlement case when cash is insufficient after T1→T0', function () {
    $trader = settlementTrader();
    [$spot, $forward] = settlementItems();
    seedCash($trader, '5000000');
    succeedForwardBuy($trader, $forward, '1', '25000000');

    SettlementService::settleOrdersForTrader($trader->id, $forward->id, now()->toDateString());

    $case = MetalSettlementCase::query()
        ->where('metal_trader_id', $trader->id)
        ->where('status', 'open')
        ->first();

    expect($case)->not->toBeNull()
        ->and($case->obligation_side)->toBe('fiat');

    $cash = MetalTraderWallet::query()
        ->where('metal_trader_id', $trader->id)
        ->whereNull('metal_item_id')
        ->first();

    expect((float) $cash->available_balance)->toBe(-20000000.0);
});

it('keeps case open after partial deposit and fulfills automatically after full deposit', function () {
    $trader = settlementTrader();
    [$spot, $forward] = settlementItems();
    seedCash($trader, '5000000');
    succeedForwardBuy($trader, $forward, '1', '25000000');

    SettlementService::settleOrdersForTrader($trader->id, $forward->id, now()->toDateString());

    $case = MetalSettlementCase::query()->where('metal_trader_id', $trader->id)->firstOrFail();
    $service = app(SettlementFulfillmentService::class);

    $service->deposit($case, '10000000');
    expect($case->fresh()->status)->toBe('open');

    $service->deposit($case->fresh(), '10000000');
    expect($case->fresh()->status)->toBe('fulfilled');

    $cash = MetalTraderWallet::query()
        ->where('metal_trader_id', $trader->id)
        ->whereNull('metal_item_id')
        ->first();

    expect((float) $cash->available_balance)->toBe(0.0);
});

it('allows manual fulfill while leaving orphan negative cash', function () {
    $trader = settlementTrader();
    [$spot, $forward] = settlementItems();
    seedCash($trader, '5000000');
    succeedForwardBuy($trader, $forward, '1', '25000000');

    SettlementService::settleOrdersForTrader($trader->id, $forward->id, now()->toDateString());
    $case = MetalSettlementCase::query()->where('metal_trader_id', $trader->id)->firstOrFail();

    app(SettlementFulfillmentService::class)->deposit($case, '10000000');
    app(SettlementFulfillmentService::class)->fulfillManually($case->fresh(), 'قبول مانده منفی');

    expect($case->fresh()->status)->toBe('fulfilled');

    $cash = MetalTraderWallet::query()
        ->where('metal_trader_id', $trader->id)
        ->whereNull('metal_item_id')
        ->first();

    expect((float) $cash->available_balance)->toBe(-10000000.0);
});

it('defaults a buy case with correction ledger and penalty_loss', function () {
    $trader = settlementTrader();
    [$spot, $forward] = settlementItems();
    seedCash($trader, '0');
    $order = succeedForwardBuy($trader, $forward, '1', '25000000');

    SettlementService::settleOrdersForTrader($trader->id, $forward->id, now()->toDateString());
    $case = MetalSettlementCase::query()->where('metal_trader_id', $trader->id)->firstOrFail();

    $service = app(SettlementFulfillmentService::class);
    $preview = $service->previewDefault($case);
    expect((int) $preview['one_percent'])->toBe(250000);

    $service->executeDefault($case, '1000000', false, 'نکول تست');

    $case = $case->fresh();
    expect($case->status)->toBe('defaulted')
        ->and((int) $case->penalty_total)->toBe(1250000);

    $spotWallet = MetalTraderWallet::query()
        ->where('metal_trader_id', $trader->id)
        ->where('metal_item_id', $spot->id)
        ->first();

    // ۱ گرم خرید تسویه شده بود؛ اصلاحیه آن را صفر می‌کند
    expect((float) $spotWallet->available_balance)->toBe(0.0);

    $cash = MetalTraderWallet::query()
        ->where('metal_trader_id', $trader->id)
        ->whereNull('metal_item_id')
        ->first();

    // قبل از نکول: -25M ؛ اصلاحیه +25M → 0 ؛ سپس penalty -1.25M → -1.25M
    expect((float) $cash->available_balance)->toBe(-1250000.0);

    expect(WalletTransaction::query()->where('metal_order_id', $order->id)->where('type', 'settlement_correction_metal')->exists())->toBeTrue()
        ->and(WalletTransaction::query()->where('metal_order_id', $order->id)->where('type', 'settlement_correction_fiat')->exists())->toBeTrue()
        ->and(WalletTransaction::query()->where('metal_settlement_case_id', $case->id)->where('type', 'penalty_loss')->exists())->toBeTrue();
});

it('opens a metal case for net sell and fulfills after delivery', function () {
    $trader = settlementTrader();
    [$spot, $forward] = settlementItems();
    succeedForwardSell($trader, $forward, '1', '25000000');

    SettlementService::settleOrdersForTrader($trader->id, $forward->id, now()->toDateString());

    $case = MetalSettlementCase::query()
        ->where('metal_trader_id', $trader->id)
        ->where('status', 'open')
        ->first();

    expect($case)->not->toBeNull()
        ->and($case->obligation_side)->toBe('metal');

    $spotWallet = MetalTraderWallet::query()
        ->where('metal_trader_id', $trader->id)
        ->where('metal_item_id', $spot->id)
        ->first();

    expect((float) $spotWallet->available_balance)->toBe(-1.0);

    $service = app(SettlementFulfillmentService::class);
    $service->recordMetalDelivery($case, '0.400');
    expect($case->fresh()->status)->toBe('open');

    $service->recordMetalDelivery($case->fresh(), '0.600');
    expect($case->fresh()->status)->toBe('fulfilled');

    $spotWallet->refresh();
    expect((float) $spotWallet->available_balance)->toBe(0.0);
});

it('opens a fiat settlement case for spot T0 buy when cash goes negative', function () {
    $trader = settlementTrader();
    [$spot] = settlementItems();

    $order = MetalOrder::query()->create([
        'tracking_code' => random_int(1000000000, 9999999999),
        'created_id' => $trader->id,
        'created_type' => 'metal_trader',
        'metal_item_id' => $spot->id,
        'order_type' => 'buy',
        'status' => 'succeed',
        'settlement_date' => now()->toDateString(),
        'settlement_status' => 'pending',
        'product_name' => $spot->title,
        'qty' => 1,
        'unit' => 'gram',
        'fee' => 25000000,
        'amount' => 25000000,
    ]);

    AssetService::updateAsset($order->fresh());

    $case = MetalSettlementCase::query()
        ->where('metal_trader_id', $trader->id)
        ->where('commitment_metal_item_id', $spot->id)
        ->where('status', 'open')
        ->first();

    expect($case)->not->toBeNull()
        ->and($case->obligation_side)->toBe('fiat')
        ->and($case->orders->pluck('id')->all())->toContain($order->id)
        ->and($order->fresh()->settlement_status)->toBe('settled');
});

it('does not open a settlement case for spot T0 buy when cash covers the amount', function () {
    $trader = settlementTrader();
    [$spot] = settlementItems();
    seedCash($trader, '25000000');

    $order = MetalOrder::query()->create([
        'tracking_code' => random_int(1000000000, 9999999999),
        'created_id' => $trader->id,
        'created_type' => 'metal_trader',
        'metal_item_id' => $spot->id,
        'order_type' => 'buy',
        'status' => 'succeed',
        'settlement_date' => now()->toDateString(),
        'settlement_status' => 'pending',
        'product_name' => $spot->title,
        'qty' => 1,
        'unit' => 'gram',
        'fee' => 25000000,
        'amount' => 25000000,
    ]);

    AssetService::updateAsset($order->fresh());

    expect(MetalSettlementCase::query()->where('metal_trader_id', $trader->id)->count())->toBe(0)
        ->and($order->fresh()->settlement_status)->toBe('settled');
});

it('opens a metal settlement case for spot T0 sell when metal goes negative', function () {
    $trader = settlementTrader();
    [$spot] = settlementItems();

    $order = MetalOrder::query()->create([
        'tracking_code' => random_int(1000000000, 9999999999),
        'created_id' => $trader->id,
        'created_type' => 'metal_trader',
        'metal_item_id' => $spot->id,
        'order_type' => 'sell',
        'status' => 'succeed',
        'settlement_date' => now()->toDateString(),
        'settlement_status' => 'pending',
        'product_name' => $spot->title,
        'qty' => 1,
        'unit' => 'gram',
        'fee' => 25000000,
        'amount' => 25000000,
    ]);

    AssetService::updateAsset($order->fresh());

    $case = MetalSettlementCase::query()
        ->where('metal_trader_id', $trader->id)
        ->where('commitment_metal_item_id', $spot->id)
        ->where('status', 'open')
        ->first();

    expect($case)->not->toBeNull()
        ->and($case->obligation_side)->toBe('metal')
        ->and($case->orders->pluck('id')->all())->toContain($order->id);
});
