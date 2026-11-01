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
beforeEach(function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Database driver unavailable: ' . $e->getMessage());
    }
});

function coverageMetalItem(int $days = 0): MetalItem
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

it('uses T0 for T1 when T1 inventory is insufficient', function () {
    $item = coverageMetalItem(1);
    $ledger = app(MetalTreasuryLedgerService::class);
    $coverage = app(MetalOrderCoverageService::class);

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
