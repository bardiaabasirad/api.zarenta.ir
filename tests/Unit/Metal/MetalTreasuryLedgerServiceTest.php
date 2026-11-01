<?php

use App\Constants\MetalTreasuryLotStatus;
use App\Constants\MetalTreasurySourceType;
use App\Models\MetalItem;
use App\Models\MetalItemGroup;
use App\Models\MetalTreasuryLot;
use App\Services\Metal\MetalTreasuryLedgerService;
/**
 * نیاز به PDO sqlite یا DB تست پروژه دارد.
 * در محیطی بدون sqlite driver این فایل skip می‌شود.
 */
beforeEach(function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Database driver unavailable: ' . $e->getMessage());
    }
});

function makeMetalItemForTreasury(int $days = 0): MetalItem
{
    $group = MetalItemGroup::query()->first()
        ?? MetalItemGroup::create(['title' => 'test-group-' . uniqid(), 'sort_order' => 1]);

    return MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $group->id,
        'title' => 'item-' . uniqid(),
        'is_spot' => $days === 0,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'unit' => 'gram',
        'settlement_working_days' => $days,
        'sort_order' => 1,
    ]);
}

it('creates lot via immutable IN movement', function () {
    $item = makeMetalItemForTreasury(0);
    $ledger = app(MetalTreasuryLedgerService::class);

    $lot = $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => 'T0',
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '10.000',
        'unit_cost' => '5000000',
    ], 'test-lot-in-1');

    expect($lot->remaining_qty)->toBe('10.000')
        ->and($lot->movements()->count())->toBe(1)
        ->and($ledger->createLot([
            'metal_item_id' => $item->id,
            'maturity_code' => 'T0',
            'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
            'initial_qty' => '99.000',
            'unit_cost' => '1',
        ], 'test-lot-in-1')->id)->toBe($lot->id);
});

it('consumes fifo and prevents negative inventory', function () {
    $item = makeMetalItemForTreasury(0);
    $ledger = app(MetalTreasuryLedgerService::class);

    $lotA = $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => 'T0',
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '3.000',
        'unit_cost' => '100',
        'purchased_at' => now()->subHour(),
    ], 'fifo-a');

    $lotB = $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => 'T0',
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '5.000',
        'unit_cost' => '200',
        'purchased_at' => now(),
    ], 'fifo-b');

    $chunks = $ledger->consumeFifo($item->id, ['T0'], '4.000', 'fifo-test');

    expect($chunks)->toHaveCount(2)
        ->and($chunks[0]['lot']->id)->toBe($lotA->id)
        ->and($chunks[0]['qty'])->toBe('3.000')
        ->and($chunks[1]['qty'])->toBe('1.000');

    expect(fn () => $ledger->recordMovement([
        'lot_id' => $lotB->id,
        'movement_type' => 'ALLOCATE',
        'qty' => '999',
        'idempotency_key' => 'neg-test',
    ]))->toThrow(LogicException::class);
});

it('allows external out only from free remaining', function () {
    $item = makeMetalItemForTreasury(0);
    $ledger = app(MetalTreasuryLedgerService::class);

    $lot = $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => 'T0',
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '10.000',
        'unit_cost' => '100',
    ], 'ext-in');

    $ledger->recordMovement([
        'lot_id' => $lot->id,
        'movement_type' => 'ALLOCATE',
        'qty' => '4.000',
        'idempotency_key' => 'ext-alloc',
    ]);

    $lot->refresh();
    expect($lot->remaining_qty)->toBe('6.000');

    $ledger->externalOut($lot, '6.000', 'ext-out-1');
    $lot->refresh();

    expect($lot->remaining_qty)->toBe('0.000')
        ->and($lot->status)->toBe(MetalTreasuryLotStatus::CLOSED_EXTERNAL);

    expect(fn () => $ledger->externalOut($lot->fresh(), '0.001', 'ext-out-2'))
        ->toThrow(LogicException::class);
});

it('partial traditional external out reduces free stock without creating coverage pnl', function () {
    $item = makeMetalItemForTreasury(0);
    $ledger = app(MetalTreasuryLedgerService::class);

    $lot = $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => 'T0',
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '100.000',
        'unit_cost' => '5000000',
    ], 'ext-partial-in');

    $movement = $ledger->externalOut($lot, '50.000', 'ext-partial-50');
    $lot->refresh();

    expect($lot->remaining_qty)->toBe('50.000')
        ->and($lot->status)->toBe(MetalTreasuryLotStatus::OPEN)
        ->and($movement->movement_type)->toBe('EXTERNAL_OUT')
        ->and(data_get($movement->meta, 'pnl_excluded'))->toBeTrue()
        ->and($movement->allocation_id)->toBeNull()
        ->and(\App\Models\MetalOrderCoverageAllocation::query()->where('lot_id', $lot->id)->count())->toBe(0);
});
