<?php

use App\Models\MetalItem;
use App\Models\MetalItemGroup;
use App\Models\MetalOrder;
use App\Models\MetalTrader;
use App\Models\MetalTraderWallet;
use App\Models\WalletTransaction;
use App\Services\AssetService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    try {
        DB::connection()->getPdo();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Database driver unavailable: ' . $e->getMessage());
    }
});

function spotWalletGroup(): MetalItemGroup
{
    return MetalItemGroup::query()->first()
        ?? MetalItemGroup::create(['title' => 'spot-wallet-group-' . uniqid(), 'sort_order' => 1]);
}

function spotWalletTrader(): MetalTrader
{
    return MetalTrader::query()->create([
        'name' => 'spot-wallet-trader-' . uniqid(),
        'phone' => (int) ('9' . random_int(100000000, 999999999)),
        'status' => 'active',
    ]);
}

function spotWalletItem(bool $isSpot = true): MetalItem
{
    return MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => spotWalletGroup()->id,
        'title' => ($isSpot ? 'spot-' : 'fwd-') . uniqid(),
        'unit' => 'gram',
        'is_spot' => $isSpot,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'settlement_working_days' => $isSpot ? 0 : 1,
        'sort_order' => 1,
    ]);
}

it('debits cash wallet on spot buy instead of metal fiat_balance', function () {
    $trader = spotWalletTrader();
    $item = spotWalletItem(true);

    $order = MetalOrder::query()->create([
        'tracking_code' => 'SW' . random_int(100000, 999999),
        'created_id' => $trader->id,
        'created_type' => 'metal_trader',
        'metal_item_id' => $item->id,
        'order_type' => 'buy',
        'status' => 'succeed',
        'settlement_date' => now()->toDateString(),
        'settlement_status' => 'pending',
        'product_name' => $item->title,
        'qty' => 1,
        'unit' => 'gram',
        'fee' => 26086142,
        'amount' => 26086142,
    ]);

    AssetService::updateAsset($order->fresh());

    $metalWallet = MetalTraderWallet::query()
        ->where('metal_trader_id', $trader->id)
        ->where('metal_item_id', $item->id)
        ->first();

    $cashWallet = MetalTraderWallet::query()
        ->where('metal_trader_id', $trader->id)
        ->whereNull('metal_item_id')
        ->first();

    expect($metalWallet)->not->toBeNull()
        ->and((float) $metalWallet->available_balance)->toBe(1.0)
        ->and((float) $metalWallet->fiat_balance)->toBe(0.0)
        ->and($cashWallet)->not->toBeNull()
        ->and((float) $cashWallet->available_balance)->toBe(-26086142.0)
        ->and($order->fresh()->settlement_status)->toBe('settled');

    $fiatTx = WalletTransaction::query()
        ->where('metal_order_id', $order->id)
        ->where('type', 'fiat_debt_in')
        ->first();

    expect($fiatTx)->not->toBeNull()
        ->and((int) $fiatTx->metal_trader_wallet_id)->toBe((int) $cashWallet->id);
});

it('keeps fiat_balance on metal wallet for forward buy', function () {
    $trader = spotWalletTrader();
    $item = spotWalletItem(false);

    $order = MetalOrder::query()->create([
        'tracking_code' => 'FW' . random_int(100000, 999999),
        'created_id' => $trader->id,
        'created_type' => 'metal_trader',
        'metal_item_id' => $item->id,
        'order_type' => 'buy',
        'status' => 'succeed',
        'settlement_date' => now()->addDay()->toDateString(),
        'settlement_status' => 'pending',
        'product_name' => $item->title,
        'qty' => 2,
        'unit' => 'gram',
        'fee' => 1000000,
        'amount' => 2000000,
    ]);

    AssetService::updateAsset($order->fresh());

    $metalWallet = MetalTraderWallet::query()
        ->where('metal_trader_id', $trader->id)
        ->where('metal_item_id', $item->id)
        ->first();

    $cashWallet = MetalTraderWallet::query()
        ->where('metal_trader_id', $trader->id)
        ->whereNull('metal_item_id')
        ->first();

    expect($metalWallet)->not->toBeNull()
        ->and((float) $metalWallet->available_balance)->toBe(2.0)
        ->and((float) $metalWallet->fiat_balance)->toBe(-2000000.0);

    if ($cashWallet) {
        expect((float) $cashWallet->available_balance)->toBe(0.0);
    }

    $fiatTx = WalletTransaction::query()
        ->where('metal_order_id', $order->id)
        ->where('type', 'fiat_debt_in')
        ->first();

    expect($fiatTx)->not->toBeNull()
        ->and((int) $fiatTx->metal_trader_wallet_id)->toBe((int) $metalWallet->id);
});
