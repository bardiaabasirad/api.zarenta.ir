<?php

use App\Constants\MetalMaturity;
use App\Models\MetalItem;
use App\Models\MetalItemGroup;
use App\Models\MetalOrder;
use App\Services\Metal\MetalMaturityService;

it('maps working days to maturity codes', function () {
    $service = new MetalMaturityService();

    expect(MetalMaturity::fromWorkingDays(0))->toBe(MetalMaturity::T0)
        ->and(MetalMaturity::fromWorkingDays(1))->toBe(MetalMaturity::T1)
        ->and(MetalMaturity::fromWorkingDays(2))->toBe(MetalMaturity::T2)
        ->and(MetalMaturity::fromWorkingDays(5))->toBe(MetalMaturity::T2);
});

it('forbids later maturities for earlier orders', function () {
    $service = new MetalMaturityService();

    expect($service->isSourceAllowed(MetalMaturity::T0, MetalMaturity::T0))->toBeTrue()
        ->and($service->isSourceAllowed(MetalMaturity::T0, MetalMaturity::T1))->toBeFalse()
        ->and($service->isSourceAllowed(MetalMaturity::T0, MetalMaturity::T2))->toBeFalse()
        ->and($service->isSourceAllowed(MetalMaturity::T1, MetalMaturity::T2))->toBeFalse()
        ->and($service->isSourceAllowed(MetalMaturity::T1, MetalMaturity::T0))->toBeTrue()
        ->and($service->isSourceAllowed(MetalMaturity::T2, MetalMaturity::T1))->toBeTrue();
});

it('orders coverage priority correctly', function () {
    expect(MetalMaturity::coveragePriority(MetalMaturity::T2))
        ->toBe([MetalMaturity::T2, MetalMaturity::T1, MetalMaturity::T0]);
});

it('includes settlement spot metal among allowed source items', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Database driver unavailable: ' . $e->getMessage());
    }

    $group = MetalItemGroup::query()->first()
        ?? MetalItemGroup::create(['title' => 'mat-group-' . uniqid(), 'sort_order' => 1]);

    $spot = MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $group->id,
        'title' => 'spot-' . uniqid(),
        'is_spot' => true,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'unit' => 'gram',
        'settlement_working_days' => 0,
        'sort_order' => 1,
    ]);

    $t1 = MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $group->id,
        'title' => 't1-' . uniqid(),
        'is_spot' => false,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'unit' => 'gram',
        'settlement_working_days' => 1,
        'settlement_metal_item_id' => $spot->id,
        'sort_order' => 1,
    ]);

    $order = new MetalOrder(['metal_item_id' => $t1->id]);
    $order->setRelation('metalItem', $t1);

    $service = new MetalMaturityService();

    expect($service->allowedSourceMetalItemIds($order))
        ->toBe([$t1->id, $spot->id])
        ->and($service->isSourceMetalItemAllowed($order, $spot->id))->toBeTrue()
        ->and($service->isSourceMetalItemAllowed($order, 999999))->toBeFalse();
});
