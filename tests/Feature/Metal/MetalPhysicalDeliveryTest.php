<?php

use App\Constants\MetalPhysicalDeliverySource;
use App\Constants\MetalPhysicalDeliveryStatus;
use App\Constants\MetalTreasuryLotStatus;
use App\Constants\MetalTreasurySourceType;
use App\Models\MetalItem;
use App\Models\MetalItemGroup;
use App\Models\MetalPhysicalDeliveryRequest;
use App\Models\MetalTrader;
use App\Models\MetalTraderWallet;
use App\Models\SelectedMetalPrice;
use App\Models\WalletTransaction;
use App\Services\Metal\MetalPhysicalDeliveryService;
use App\Services\Metal\MetalTreasuryLedgerService;

beforeEach(function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Database driver unavailable: ' . $e->getMessage());
    }

    if (!\Illuminate\Support\Facades\DB::table('settings')
        ->where('option_key', MetalPhysicalDeliveryService::SETTING_FEE_PERCENT)
        ->exists()
    ) {
        \Illuminate\Support\Facades\DB::table('settings')->insert([
            'option_key' => MetalPhysicalDeliveryService::SETTING_FEE_PERCENT,
            'option_name' => 'نرخ کارمزد تحویل فیزیکی فلز (درصد)',
            'option_value' => '0.5',
        ]);
    } else {
        \Illuminate\Support\Facades\DB::table('settings')
            ->where('option_key', MetalPhysicalDeliveryService::SETTING_FEE_PERCENT)
            ->update(['option_value' => '0.5']);
    }
});

function pdrSpotItem(): MetalItem
{
    $group = MetalItemGroup::query()->first()
        ?? MetalItemGroup::create(['title' => 'pdr-group-' . uniqid(), 'sort_order' => 1]);

    return MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $group->id,
        'title' => 'pdr-spot-' . uniqid(),
        'is_spot' => true,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'unit' => 'gram',
        'pricing_unit_id' => 'gram',
        'settlement_working_days' => 0,
        'sort_order' => 1,
    ]);
}

function pdrForwardItem(): MetalItem
{
    $group = MetalItemGroup::query()->first()
        ?? MetalItemGroup::create(['title' => 'pdr-group-' . uniqid(), 'sort_order' => 1]);

    return MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => $group->id,
        'title' => 'pdr-fwd-' . uniqid(),
        'is_spot' => false,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'unit' => 'gram',
        'pricing_unit_id' => 'gram',
        'settlement_working_days' => 1,
        'sort_order' => 1,
    ]);
}

function pdrTrader(): MetalTrader
{
    return MetalTrader::query()->create([
        'name' => 'trader-' . uniqid(),
        'phone' => (string) random_int(9000000000, 9999999999),
        'api_key' => bin2hex(random_bytes(16)),
        'status' => 'active',
    ]);
}

function pdrSeedPrice(MetalItem $item, string $sell = '1000000'): void
{
    SelectedMetalPrice::query()->create([
        'metal_item_id' => $item->id,
        'buy' => $sell,
        'sell' => $sell,
        'time' => now(),
    ]);
}

function pdrMetalWallet(MetalTrader $trader, MetalItem $item, string $available = '10.000'): MetalTraderWallet
{
    return MetalTraderWallet::query()->create([
        'metal_trader_id' => $trader->id,
        'metal_item_id' => $item->id,
        'available_balance' => $available,
        'blocked_balance' => '0',
        'fiat_balance' => '0',
        'avg_buy_price' => '900000',
    ]);
}

function pdrFiatWallet(MetalTrader $trader, string $balance = '1000'): MetalTraderWallet
{
    return MetalTraderWallet::query()->create([
        'metal_trader_id' => $trader->id,
        'metal_item_id' => null,
        'available_balance' => $balance,
        'blocked_balance' => '0',
        'fiat_balance' => '0',
        'avg_buy_price' => '0',
    ]);
}

it('reserves T0 metal and charges fee allowing negative fiat without changing MWAC', function () {
    $item = pdrSpotItem();
    pdrSeedPrice($item, '1000000');
    $trader = pdrTrader();
    $metalWallet = pdrMetalWallet($trader, $item, '10.000');
    $fiat = pdrFiatWallet($trader, '100'); // کمتر از کارمزد → منفی مجاز
    $avgBefore = (string) $metalWallet->avg_buy_price;

    $svc = app(MetalPhysicalDeliveryService::class);
    // 5g * 1_000_000 * 0.5% = 25_000
    $req = $svc->create($trader, $item->id, '5.000', 'pdr-create-' . uniqid());

    $metalWallet->refresh();
    $fiat->refresh();

    expect($req->status)->toBe(MetalPhysicalDeliveryStatus::PENDING)
        ->and($req->fee_amount)->toBe('25000')
        ->and($req->fee_rate_percent)->toBe('0.500000')
        ->and($metalWallet->available_balance)->toBe('5.000')
        ->and($metalWallet->blocked_balance)->toBe('5.000')
        ->and($metalWallet->avg_buy_price)->toBe($avgBefore)
        ->and(bccomp((string) $fiat->available_balance, '-24900', 0))->toBe(0)
        ->and(WalletTransaction::where('metal_physical_delivery_request_id', $req->id)->where('type', 'withdraw')->count())->toBe(1);
});

it('rejects non-spot metal and prevents double spend of reserved weight', function () {
    $fwd = pdrForwardItem();
    pdrSeedPrice($fwd, '1000000');
    $trader = pdrTrader();
    pdrMetalWallet($trader, $fwd, '10.000');
    pdrFiatWallet($trader, '1000000');
    $svc = app(MetalPhysicalDeliveryService::class);

    expect(fn () => $svc->create($trader, $fwd->id, '1.000'))
        ->toThrow(LogicException::class);

    $item = pdrSpotItem();
    pdrSeedPrice($item, '1000000');
    pdrMetalWallet($trader, $item, '10.000');

    $svc->create($trader, $item->id, '6.000', 'pdr-a-' . uniqid());
    expect(fn () => $svc->create($trader, $item->id, '5.000', 'pdr-b-' . uniqid()))
        ->toThrow(LogicException::class);
});

it('cancels from READY_FOR_PICKUP and refunds exact fee once', function () {
    $item = pdrSpotItem();
    pdrSeedPrice($item, '1000000');
    $trader = pdrTrader();
    pdrMetalWallet($trader, $item, '10.000');
    $fiat = pdrFiatWallet($trader, '100000');
    $svc = app(MetalPhysicalDeliveryService::class);

    $req = $svc->create($trader, $item->id, '4.000', 'pdr-cancel-' . uniqid());
    $fee = (string) $req->fee_amount;

    $req = $svc->markReady($req);
    expect($req->status)->toBe(MetalPhysicalDeliveryStatus::READY_FOR_PICKUP);

    $req = $svc->cancelByTrader($req, $trader);
    $fiat->refresh();
    $metal = MetalTraderWallet::query()->whereKey($req->metal_trader_wallet_id)->first();

    expect($req->status)->toBe(MetalPhysicalDeliveryStatus::CANCELLED)
        ->and($req->fee_refunded_at)->not->toBeNull()
        ->and($metal->available_balance)->toBe('10.000')
        ->and($metal->blocked_balance)->toBe('0.000')
        ->and(bccomp((string) $fiat->available_balance, '100000', 0))->toBe(0)
        ->and(WalletTransaction::where('metal_physical_delivery_request_id', $req->id)->where('type', 'deposit')->count())->toBe(1)
        ->and(bccomp((string) WalletTransaction::where('metal_physical_delivery_request_id', $req->id)->where('type', 'deposit')->value('amount'), $fee, 0))->toBe(0);

    expect(fn () => $svc->cancelByTrader($req->fresh(), $trader))->toThrow(LogicException::class);
});

it('delivers from treasury physical lot and recognizes fee without second fiat tx', function () {
    $item = pdrSpotItem();
    pdrSeedPrice($item, '1000000');
    $trader = pdrTrader();
    pdrMetalWallet($trader, $item, '10.000');
    $fiat = pdrFiatWallet($trader, '100000');
    $svc = app(MetalPhysicalDeliveryService::class);
    $ledger = app(MetalTreasuryLedgerService::class);

    $lot = $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => 'T0',
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '20.000',
        'unit_cost' => '900000',
    ], 'pdr-lot-' . uniqid());

    expect((int) $lot->metal_item_id)->toBe((int) $item->id);

    $req = $svc->create($trader, $item->id, '5.000', 'pdr-del-' . uniqid());
    $withdrawCount = WalletTransaction::where('metal_physical_delivery_request_id', $req->id)->where('type', 'withdraw')->count();

    expect((int) $req->metal_item_id)->toBe((int) $item->id)
        ->and((int) $lot->metal_item_id)->toBe((int) $req->metal_item_id);

    $deliverKey = 'deliver-idem-' . uniqid();
    $req = $svc->deliver($req->fresh(), [
        'source' => MetalPhysicalDeliverySource::TREASURY_PHYSICAL,
        'lot_id' => $lot->id,
        'idempotency_key' => $deliverKey,
    ]);

    $lot->refresh();
    $metal = MetalTraderWallet::query()->whereKey($req->metal_trader_wallet_id)->first();
    $fiat->refresh();

    expect($req->status)->toBe(MetalPhysicalDeliveryStatus::DELIVERED)
        ->and($req->fee_recognized_at)->not->toBeNull()
        ->and($req->delivery_source)->toBe(MetalPhysicalDeliverySource::TREASURY_PHYSICAL)
        ->and($lot->remaining_qty)->toBe('15.000')
        ->and($metal->blocked_balance)->toBe('0.000')
        ->and($metal->available_balance)->toBe('5.000')
        ->and(WalletTransaction::where('metal_physical_delivery_request_id', $req->id)->where('type', 'withdraw')->count())->toBe($withdrawCount)
        ->and(WalletTransaction::where('metal_physical_delivery_request_id', $req->id)->where('type', 'deposit')->count())->toBe(0);

    $again = $svc->deliver($req->fresh(), [
        'source' => MetalPhysicalDeliverySource::TREASURY_PHYSICAL,
        'lot_id' => $lot->id,
        'idempotency_key' => $deliverKey,
    ]);
    expect($again->id)->toBe($req->id);
    $lot->refresh();
    expect($lot->remaining_qty)->toBe('15.000');
});

it('keeps request status when treasury free qty is insufficient', function () {
    $item = pdrSpotItem();
    pdrSeedPrice($item, '1000000');
    $trader = pdrTrader();
    pdrMetalWallet($trader, $item, '10.000');
    pdrFiatWallet($trader, '100000');
    $svc = app(MetalPhysicalDeliveryService::class);
    $ledger = app(MetalTreasuryLedgerService::class);

    $lot = $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => 'T0',
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '2.000',
        'unit_cost' => '900000',
    ], 'pdr-lot-small');

    $req = $svc->create($trader, $item->id, '5.000', 'pdr-insuff-' . uniqid());
    expect(fn () => $svc->deliver($req, [
        'source' => MetalPhysicalDeliverySource::TREASURY_PHYSICAL,
        'lot_id' => $lot->id,
    ]))->toThrow(LogicException::class);

    $req->refresh();
    $lot->refresh();
    expect($req->status)->toBe(MetalPhysicalDeliveryStatus::PENDING)
        ->and($lot->remaining_qty)->toBe('2.000')
        ->and($req->fee_recognized_at)->toBeNull();
});

it('delivers from shop unregistered with audit only and no lot movement', function () {
    $item = pdrSpotItem();
    pdrSeedPrice($item, '1000000');
    $trader = pdrTrader();
    pdrMetalWallet($trader, $item, '10.000');
    pdrFiatWallet($trader, '100000');
    $svc = app(MetalPhysicalDeliveryService::class);
    $ledger = app(MetalTreasuryLedgerService::class);

    $lot = $ledger->createLot([
        'metal_item_id' => $item->id,
        'maturity_code' => 'T0',
        'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
        'initial_qty' => '50.000',
        'unit_cost' => '900000',
    ], 'pdr-lot-shop');

    $req = $svc->create($trader, $item->id, '3.000', 'pdr-shop-' . uniqid());
    $req = $svc->deliver($req, [
        'source' => MetalPhysicalDeliverySource::SHOP_UNREGISTERED,
        'notes' => 'تحویل از ویترین',
    ]);

    $lot->refresh();
    expect($req->status)->toBe(MetalPhysicalDeliveryStatus::DELIVERED)
        ->and($req->delivery_source)->toBe(MetalPhysicalDeliverySource::SHOP_UNREGISTERED)
        ->and($req->treasury_lot_id)->toBeNull()
        ->and($req->treasury_movement_id)->toBeNull()
        ->and($lot->remaining_qty)->toBe('50.000')
        ->and($lot->status)->toBe(MetalTreasuryLotStatus::OPEN)
        ->and($req->meta['shop_unregistered_audit'] ?? false)->toBeTrue();
});

it('is idempotent on create key and snapshots fee rate for new requests only', function () {
    $item = pdrSpotItem();
    pdrSeedPrice($item, '1000000');
    $trader = pdrTrader();
    pdrMetalWallet($trader, $item, '20.000');
    pdrFiatWallet($trader, '1000000');
    $svc = app(MetalPhysicalDeliveryService::class);

    $sameKey = 'same-key-' . uniqid();
    $a = $svc->create($trader, $item->id, '2.000', $sameKey);
    $b = $svc->create($trader, $item->id, '2.000', $sameKey);
    expect($a->id)->toBe($b->id)
        ->and(MetalPhysicalDeliveryRequest::where('idempotency_key', $sameKey)->count())->toBe(1);

    $svc->updateFeeRatePercent('1');
    $c = $svc->create($trader, $item->id, '2.000', 'new-key-' . uniqid());
    expect($a->fee_rate_percent)->toBe('0.500000')
        ->and($c->fee_rate_percent)->toBe('1.000000');
});
