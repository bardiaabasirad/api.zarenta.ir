<?php

use App\Constants\MetalCoverageStatus;
use App\Constants\MetalMaturity;
use App\Constants\MetalTreasurySourceType;
use App\Models\MetalItem;
use App\Models\MetalItemGroup;
use App\Models\MetalOrder;
use App\Models\MetalOrderCoverageAllocation;
use App\Models\MetalTrader;
use App\Services\Metal\MetalOrderCoverageService;
use App\Services\Metal\MetalTreasuryLedgerService;
use InvalidArgumentException;
beforeEach(function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Database driver unavailable: ' . $e->getMessage());
    }
});

function coverageMetalItem(int $days = 0, ?int $settlementMetalItemId = null): MetalItem
{
    $group = MetalItemGroup::query()->first()
        ?? MetalItemGroup::create(['title' => 'cov-group-' . uniqid(), 'sort_order' => 1]);

    return MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $group->id,
        'title' => 'cov-item-' . uniqid(),
        'is_spot' => $days === 0,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'unit' => 'gram',
        'settlement_working_days' => $days,
        'settlement_metal_item_id' => $settlementMetalItemId,
        'sort_order' => 1,
    ]);
}

function coverageBuyOrder(MetalItem $item, string $qty = '10.000', string $fee = '5000000'): MetalOrder
{
    return MetalOrder::create([
        'tracking_code' => random_int(1000000000, 9999999999),
        'metal_item_id' => $item->id,
        'product_name' => $item->title,
        'qty' => $qty,
        'unit' => 'gram',
        'fee' => $fee,
        'amount' => (int) round((float) $qty * (float) $fee),
        'created_id' => 1,
        'created_type' => 'metal_trader',
        'status' => 'processing',
        'order_type' => 'buy',
        'settlement_date' => now()->toDateString(),
        'settlement_status' => 'settled',
        'extra_data' => [],
    ]);
}

it('fully covers a buy order from treasury fifo lots', function () {
    $item = coverageMetalItem(0);
    $ledger = app(MetalTreasuryLedgerService::class);
    $coverage = app(MetalOrderCoverageService::class);

    $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => MetalMaturity::T0,
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '4.000',
        'unit_cost' => '4800000',
        'purchased_at' => now()->subMinutes(10),
    ], 'cov-full-a');

    $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => MetalMaturity::T0,
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '6.000',
        'unit_cost' => '4900000',
        'purchased_at' => now()->subMinutes(5),
    ], 'cov-full-b');

    $order = coverageBuyOrder($item, '10.000', '5000000');

    $order = $coverage->coverBuyOrder($order, 'test-full-cover', false);

    expect($order->coverage_status)->toBe(MetalCoverageStatus::FULLY_COVERED)
        ->and($order->open_qty)->toBe('0.000')
        ->and(MetalOrderCoverageAllocation::where('metal_order_id', $order->id)->count())->toBe(2);

    $pnl = $coverage->totalRealizedPnl($order);
    // 4*(5000000-4800000) + 6*(5000000-4900000) = 800000 + 600000 = 1400000
    expect($pnl)->toBe('1400000');
});

it('creates open deficit when treasury is empty and partner is skipped', function () {
    $item = coverageMetalItem(0);
    $coverage = app(MetalOrderCoverageService::class);
    $order = coverageBuyOrder($item, '5.000');

    $order = $coverage->coverBuyOrder($order, 'test-empty', false);

    expect($order->coverage_status)->toBe(MetalCoverageStatus::UNCOVERED)
        ->and($order->open_qty)->toBe('5.000')
        ->and($order->status)->not->toBe('rejected');
});

it('leaves remainder open after partial treasury when partner is not attempted', function () {
    $item = coverageMetalItem(0);
    $ledger = app(MetalTreasuryLedgerService::class);
    $coverage = app(MetalOrderCoverageService::class);

    $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => MetalMaturity::T0,
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '3.000',
        'unit_cost' => '4800000',
    ], 'cov-partial-then-partner-skip');

    $order = coverageBuyOrder($item, '10.000');
    $order = $coverage->coverBuyOrder($order, 'test-partial-no-partner', false);

    expect($order->coverage_status)->toBe(MetalCoverageStatus::PARTIALLY_COVERED)
        ->and($order->covered_qty)->toBe('3.000')
        ->and($order->open_qty)->toBe('7.000');
});

it('partially covers and leaves open qty', function () {
    $item = coverageMetalItem(0);
    $ledger = app(MetalTreasuryLedgerService::class);
    $coverage = app(MetalOrderCoverageService::class);

    $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => MetalMaturity::T0,
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '3.000',
        'unit_cost' => '4800000',
    ], 'cov-partial');

    $order = coverageBuyOrder($item, '10.000');
    $order = $coverage->coverBuyOrder($order, 'test-partial', false);

    expect($order->coverage_status)->toBe(MetalCoverageStatus::PARTIALLY_COVERED)
        ->and($order->covered_qty)->toBe('3.000')
        ->and($order->open_qty)->toBe('7.000')
        ->and($order->status)->toBe('processing');
});

it('does not use T2 inventory for T1 orders', function () {
    $item = coverageMetalItem(1);
    $ledger = app(MetalTreasuryLedgerService::class);
    $coverage = app(MetalOrderCoverageService::class);

    $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => MetalMaturity::T2,
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '10.000',
        'unit_cost' => '4800000',
    ], 'cov-t2-forbidden');

    $order = coverageBuyOrder($item, '5.000');
    $order = $coverage->coverBuyOrder($order, 'test-t2-forbid', false);

    expect($order->covered_qty)->toBe('0.000')
        ->and($order->coverage_status)->toBe(MetalCoverageStatus::UNCOVERED);
});

it('does not use T0 for T1 when operator toggle is off', function () {
    $item = coverageMetalItem(1);
    $ledger = app(MetalTreasuryLedgerService::class);
    $coverage = app(MetalOrderCoverageService::class);

    \App\Models\Setting::query()->firstOrNew(['option_key' => \App\Services\Metal\MetalMaturityService::SETTING_ALLOW_T0_COVER_T1])
        ->forceFill(['option_name' => 'allow_t0_cover_t1', 'option_value' => '0'])
        ->save();
    \Illuminate\Support\Facades\Cache::forget('settings.' . \App\Services\Metal\MetalMaturityService::SETTING_ALLOW_T0_COVER_T1);

    $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => MetalMaturity::T0,
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '10.000',
        'unit_cost' => '4700000',
    ], 'cov-t0-blocked-t1');

    $order = coverageBuyOrder($item, '5.000');
    $order = $coverage->coverBuyOrder($order, 'test-t0-blocked-t1', false);

    expect($order->covered_qty)->toBe('0.000')
        ->and($order->coverage_status)->toBe(MetalCoverageStatus::UNCOVERED);
});

it('does not use T0 for T2 when operator toggle is off', function () {
    $item = coverageMetalItem(2);
    $ledger = app(MetalTreasuryLedgerService::class);
    $coverage = app(MetalOrderCoverageService::class);

    \App\Models\Setting::query()->firstOrNew(['option_key' => \App\Services\Metal\MetalMaturityService::SETTING_ALLOW_T0_COVER_T2])
        ->forceFill(['option_name' => 'allow_t0_cover_t2', 'option_value' => '0'])
        ->save();
    \Illuminate\Support\Facades\Cache::forget('settings.' . \App\Services\Metal\MetalMaturityService::SETTING_ALLOW_T0_COVER_T2);

    $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => MetalMaturity::T0,
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '10.000',
        'unit_cost' => '4700000',
    ], 'cov-t0-blocked-t2');

    $order = coverageBuyOrder($item, '5.000');
    $order = $coverage->coverBuyOrder($order, 'test-t0-blocked-t2', false);

    expect($order->covered_qty)->toBe('0.000')
        ->and($order->coverage_status)->toBe(MetalCoverageStatus::UNCOVERED);
});

it('uses T0 for T1 when T1 inventory is insufficient and toggle is on', function () {
    $item = coverageMetalItem(1);
    $ledger = app(MetalTreasuryLedgerService::class);
    $coverage = app(MetalOrderCoverageService::class);

    \App\Models\Setting::query()->firstOrNew(['option_key' => \App\Services\Metal\MetalMaturityService::SETTING_ALLOW_T0_COVER_T1])
        ->forceFill(['option_name' => 'allow_t0_cover_t1', 'option_value' => '1'])
        ->save();
    \Illuminate\Support\Facades\Cache::forget('settings.' . \App\Services\Metal\MetalMaturityService::SETTING_ALLOW_T0_COVER_T1);

    $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => MetalMaturity::T1,
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '2.000',
        'unit_cost' => '4800000',
    ], 'cov-t1');

    $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => MetalMaturity::T0,
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '5.000',
        'unit_cost' => '4700000',
    ], 'cov-t0');

    $order = coverageBuyOrder($item, '5.000');
    $order = $coverage->coverBuyOrder($order, 'test-t1-fallback', false);

    expect($order->coverage_status)->toBe(MetalCoverageStatus::FULLY_COVERED)
        ->and(MetalOrderCoverageAllocation::where('metal_order_id', $order->id)->pluck('maturity_code')->all())
        ->toContain(MetalMaturity::T1, MetalMaturity::T0);
});

it('marks overdue open coverages', function () {
    $item = coverageMetalItem(0);
    $coverage = app(MetalOrderCoverageService::class);
    $order = coverageBuyOrder($item, '2.000');
    $coverage->initializeCoverageFields($order);
    $order->coverage_deadline = now()->subDay()->toDateString();
    $order->coverage_status = MetalCoverageStatus::UNCOVERED;
    $order->open_qty = '2.000';
    $order->covered_qty = '0.000';
    $order->save();

    $count = $coverage->markOverdueOrders(now()->toDateString());

    expect($count)->toBeGreaterThanOrEqual(1)
        ->and($order->fresh()->coverage_status)->toBe(MetalCoverageStatus::OVERDUE);
});

it('reverses allocation with release movement', function () {
    $item = coverageMetalItem(0);
    $ledger = app(MetalTreasuryLedgerService::class);
    $coverage = app(MetalOrderCoverageService::class);

    $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => MetalMaturity::T0,
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '5.000',
        'unit_cost' => '4800000',
    ], 'cov-rev');

    $order = coverageBuyOrder($item, '5.000');
    $order = $coverage->coverBuyOrder($order, 'test-rev', false);

    $allocation = MetalOrderCoverageAllocation::where('metal_order_id', $order->id)->first();
    $coverage->reverseAllocation($allocation);

    expect($allocation->fresh()->reversed_at)->not->toBeNull()
        ->and($order->fresh()->open_qty)->toBe('5.000');
});

it('covers T1 commitment order from settlement spot T0 inventory', function () {
    $spot = coverageMetalItem(0);
    $t1 = coverageMetalItem(1, $spot->id);
    $ledger = app(MetalTreasuryLedgerService::class);
    $coverage = app(MetalOrderCoverageService::class);

    \App\Models\Setting::query()->firstOrNew(['option_key' => \App\Services\Metal\MetalMaturityService::SETTING_ALLOW_T0_COVER_T1])
        ->forceFill(['option_name' => 'allow_t0_cover_t1', 'option_value' => '1'])
        ->save();
    \Illuminate\Support\Facades\Cache::forget('settings.' . \App\Services\Metal\MetalMaturityService::SETTING_ALLOW_T0_COVER_T1);

    $ledger->createLot([
        'metal_item_id' => $spot->id,
        'maturity_code' => MetalMaturity::T0,
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '10.000',
        'unit_cost' => '5000000',
    ], 'cov-spot-for-t1');

    $order = coverageBuyOrder($t1, '0.500', '5100000');
    $order = $coverage->allocateFromTreasury($order, '0.500', 'alloc-spot-cross');

    expect($order->coverage_status)->toBe(MetalCoverageStatus::FULLY_COVERED)
        ->and($order->open_qty)->toBe('0.000')
        ->and(MetalOrderCoverageAllocation::where('metal_order_id', $order->id)->value('lot_id'))
        ->not->toBeNull();
});

it('auto-allocates user sell to open T2 buy; unmatched remainder stays in treasury when not sold to partner', function () {
    $item = coverageMetalItem(2);
    $coverage = app(MetalOrderCoverageService::class);

    $buy = coverageBuyOrder($item, '1.000', '5000000');
    $buy = $coverage->coverBuyOrder($buy, 'test-t2-buy-open', false);

    expect($buy->coverage_status)->toBe(MetalCoverageStatus::UNCOVERED)
        ->and($buy->open_qty)->toBe('1.000');

    $sell = MetalOrder::create([
        'tracking_code' => random_int(1000000000, 9999999999),
        'metal_item_id' => $item->id,
        'product_name' => $item->title,
        'qty' => '2.000',
        'unit' => 'gram',
        'fee' => '4900000',
        'amount' => 9800000,
        'created_id' => 1,
        'created_type' => 'metal_trader',
        'status' => 'succeed',
        'order_type' => 'sell',
        'settlement_date' => now()->addDays(2)->toDateString(),
        'settlement_status' => 'pending',
        'extra_data' => [],
    ]);

    $lot = $coverage->ingestUserSellOrder($sell);

    expect($buy->fresh()->coverage_status)->toBe(MetalCoverageStatus::FULLY_COVERED)
        ->and($buy->fresh()->covered_qty)->toBe('1.000')
        ->and($buy->fresh()->open_qty)->toBe('0.000');

    $alloc = MetalOrderCoverageAllocation::where('metal_order_id', $buy->id)->first();
    expect($alloc)->not->toBeNull()
        ->and($alloc->source_type)->toBe(MetalTreasurySourceType::USER_WALLET_SELL)
        ->and($alloc->qty)->toBe('1.000');

    // لات تطبیق‌شده مصرف شده؛ مابقی در لات جداگانه خزانه می‌ماند (چون به همکار فروخته نشده)
    $remainderLot = \App\Models\MetalTreasuryLot::query()
        ->where('source_type', MetalTreasurySourceType::USER_WALLET_SELL)
        ->where('source_id', $sell->id)
        ->where('remaining_qty', '>', 0)
        ->first();

    expect($remainderLot)->not->toBeNull()
        ->and($remainderLot->maturity_code)->toBe(MetalMaturity::T2)
        ->and($remainderLot->remaining_qty)->toBe('1.000')
        ->and($lot)->not->toBeNull();
});

it('does not put partner-sold sell remainder into treasury', function () {
    $item = coverageMetalItem(2);
    $coverage = app(MetalOrderCoverageService::class);

    $buy = coverageBuyOrder($item, '1.000', '5000000');
    $coverage->coverBuyOrder($buy, 'test-t2-buy-partner-skip', false);

    $sell = MetalOrder::create([
        'tracking_code' => random_int(1000000000, 9999999999),
        'metal_item_id' => $item->id,
        'product_name' => $item->title,
        'qty' => '2.000',
        'unit' => 'gram',
        'fee' => '4900000',
        'amount' => 9800000,
        'created_id' => 1,
        'created_type' => 'metal_trader',
        'status' => 'succeed',
        'order_type' => 'sell',
        'settlement_date' => now()->addDays(2)->toDateString(),
        'settlement_status' => 'pending',
        'extra_data' => [
            'matched_to_buys_qty' => '1.000',
            'partner_sell_qty' => '1.000',
            'auto_order_quantity' => 1.0,
        ],
    ]);

    $coverage->ingestUserSellOrder($sell);

    $lots = \App\Models\MetalTreasuryLot::query()
        ->where('source_type', MetalTreasurySourceType::USER_WALLET_SELL)
        ->where('source_id', $sell->id)
        ->get();

    expect($lots)->toHaveCount(1)
        ->and($lots->first()->initial_qty)->toBe('1.000')
        ->and($lots->sum(fn ($l) => (float) $l->initial_qty))->toBe(1.0)
        ->and($buy->fresh()->coverage_status)->toBe(MetalCoverageStatus::FULLY_COVERED);
});

it('requires sale rate and records pnl when traditionally exiting user-sell lot', function () {
    $item = coverageMetalItem(0);
    $ledger = app(MetalTreasuryLedgerService::class);
    $coverage = app(MetalOrderCoverageService::class);

    $sellOrder = MetalOrder::create([
        'tracking_code' => random_int(1000000000, 9999999999),
        'metal_item_id' => $item->id,
        'product_name' => $item->title,
        'qty' => '10.000',
        'unit' => 'gram',
        'fee' => '5000000',
        'amount' => 50000000,
        'created_id' => 1,
        'created_type' => 'metal_trader',
        'status' => 'succeed',
        'order_type' => 'sell',
        'settlement_date' => now()->toDateString(),
        'settlement_status' => 'settled',
        'extra_data' => [],
    ]);

    $lot = $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => MetalMaturity::T0,
        'source_type' => MetalTreasurySourceType::USER_WALLET_SELL,
        'source_id' => $sellOrder->id,
        'initial_qty' => '10.000',
        'unit_cost' => '5000000',
    ], 'user-sell-lot-eo');

    expect(fn () => $coverage->externalOutFromPhysicalLot($lot->id, '4.000', 'eo-no-rate'))
        ->toThrow(InvalidArgumentException::class);

    $result = $coverage->externalOutFromPhysicalLot(
        $lot->id,
        '4.000',
        'eo-with-rate',
        'بازار سنتی',
        '5200000',
    );

    expect($result['lot']->remaining_qty)->toBe('6.000')
        ->and($result['realized_pnl'])->toBe('800000') // 4*(5200000-5000000)
        ->and($result['allocation'])->not->toBeNull()
        ->and($result['allocation']->source_type)->toBe(MetalTreasurySourceType::TRADITIONAL_EXIT)
        ->and($result['allocation']->metal_order_id)->toBe($sellOrder->id)
        ->and($result['allocation']->customer_unit_price)->toBe('5200000')
        ->and($result['allocation']->coverage_unit_cost)->toBe('5000000');
});

it('keeps physical traditional exit without pnl allocation', function () {
    $item = coverageMetalItem(0);
    $ledger = app(MetalTreasuryLedgerService::class);
    $coverage = app(MetalOrderCoverageService::class);

    $lot = $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => MetalMaturity::T0,
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '8.000',
        'unit_cost' => '5100000',
    ], 'phys-eo-lot');

    $result = $coverage->externalOutFromPhysicalLot($lot->id, '3.000', 'phys-eo-1', 'خروج حضوری');

    expect($result['lot']->remaining_qty)->toBe('5.000')
        ->and($result['allocation'])->toBeNull()
        ->and($result['realized_pnl'])->toBeNull()
        ->and(MetalOrderCoverageAllocation::where('lot_id', $lot->id)->count())->toBe(0);
});

it('registers physical purchase without realized pnl until allocated', function () {
    $item = coverageMetalItem(0);
    $coverage = app(MetalOrderCoverageService::class);

    $purchase = $coverage->registerPhysicalPurchase([
        'metal_item_id' => $item->id,
        'maturity_code' => MetalMaturity::T0,
        'qty' => '20.000',
        'unit_cost' => '5100000',
        'document_number' => 'DOC-1',
        'idempotency_key' => 'phys-1',
    ]);

    expect($purchase->lot)->not->toBeNull()
        ->and($purchase->lot->remaining_qty)->toBe('20.000');

    $order = coverageBuyOrder($item, '5.000', '5200000');
    $order = $coverage->allocatePhysicalToOrder($order, $purchase->lot->id, '5.000', 'phys-alloc-1');

    $alloc = MetalOrderCoverageAllocation::where('metal_order_id', $order->id)->first();
    expect($alloc->source_type)->toBe(MetalTreasurySourceType::PHYSICAL_PURCHASE)
        ->and($alloc->realized_pnl)->toBe('500000'); // 5*(5200000-5100000)
});
