<?php

use App\Constants\AppConstants;
use App\Models\MetalItem;
use App\Models\MetalItemGroup;
use App\Models\MetalOrder;
use App\Models\MetalTrader;
use App\Models\MetalTraderWallet;
use App\Models\SelectedMetalPrice;
use App\Services\Metal\MetalOrderAssetRiskService;

beforeEach(function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Database driver unavailable: ' . $e->getMessage());
    }
});

function assetRiskGroup(): MetalItemGroup
{
    return MetalItemGroup::query()->first()
        ?? MetalItemGroup::create(['title' => 'asset-risk-group-' . uniqid(), 'sort_order' => 1]);
}

function assetRiskTrader(float $threshold = 10): MetalTrader
{
    return MetalTrader::query()->create([
        'name' => 'risk-trader-' . uniqid(),
        'phone' => (int) ('9' . random_int(100000000, 999999999)),
        'api_key' => MetalTrader::generateApiKey(),
        'status' => 'active',
        'asset_risk_threshold' => $threshold,
    ]);
}

function assetRiskGramItem(): MetalItem
{
    return MetalItem::withoutGlobalScope('visible')->create([
        'metal_item_group_id' => assetRiskGroup()->id,
        'title' => 'risk-item-' . uniqid(),
        'is_spot' => false,
        'is_buy_active' => true,
        'is_sell_active' => true,
        'is_visible' => true,
        'unit' => 'gram',
        'pricing_unit_id' => 'gram',
        'settlement_working_days' => 0,
        'sort_order' => 1,
    ]);
}

function assetRiskOrder(MetalTrader $trader, float $amount, string $type = 'buy'): MetalOrder
{
    return MetalOrder::create([
        'tracking_code' => random_int(1000000000, 9999999999),
        'created_id' => $trader->id,
        'created_type' => 'metal_trader',
        'order_type' => $type,
        'status' => 'pending',
        'product_name' => 'test',
        'qty' => 1,
        'unit' => 'gram',
        'fee' => $amount,
        'amount' => $amount,
        'extra_data' => [],
    ]);
}

it('values fiat wallet as toman and computes ratio against order amount', function () {
    $trader = assetRiskTrader(10);
    $orderAmount = 1_000_000;

    MetalTraderWallet::query()->create([
        'metal_trader_id' => $trader->id,
        'metal_item_id' => null,
        'available_balance' => '50000',
        'blocked_balance' => '0',
        'fiat_balance' => '0',
        'avg_buy_price' => '0',
    ]);

    $order = assetRiskOrder($trader, $orderAmount, 'buy');
    $result = app(MetalOrderAssetRiskService::class)->assess($order, $trader);

    expect($result)->not->toBeNull()
        ->and($result['total_assets_toman'])->toBe(50000.0)
        ->and($result['order_amount'])->toBe(1000000.0)
        ->and($result['asset_ratio_percent'])->toBe(5.0)
        ->and($result['threshold_percent'])->toBe(10.0)
        ->and($result['shortage_toman'])->toBe(50000.0)
        ->and($result['is_sufficient'])->toBeFalse();
});

it('marks order sufficient when assets meet trader threshold for sell orders too', function () {
    $trader = assetRiskTrader(10);
    $orderAmount = 1_000_000;

    MetalTraderWallet::query()->create([
        'metal_trader_id' => $trader->id,
        'metal_item_id' => null,
        'available_balance' => '200000',
        'blocked_balance' => '0',
        'fiat_balance' => '0',
        'avg_buy_price' => '0',
    ]);

    $order = assetRiskOrder($trader, $orderAmount, 'sell');
    $result = app(MetalOrderAssetRiskService::class)->assess($order, $trader);

    expect($result['asset_ratio_percent'])->toBe(20.0)
        ->and($result['is_sufficient'])->toBeTrue()
        ->and($result['shortage_toman'])->toBe(0.0);
});

it('values gram metal wallets with conversion factor and includes fiat_balance', function () {
    $trader = assetRiskTrader(10);
    $item = assetRiskGramItem();

    // مظنه مثقالی: با ضریب تبدیل به حدود 1_000_000 تومان برای هر گرم می‌رسد
    SelectedMetalPrice::query()->create([
        'metal_item_id' => $item->id,
        'buy' => AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR * 1_000_000,
        'sell' => AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR * 1_000_000,
        'time' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    MetalTraderWallet::query()->create([
        'metal_trader_id' => $trader->id,
        'metal_item_id' => $item->id,
        'available_balance' => '1',
        'blocked_balance' => '0',
        'fiat_balance' => '50000',
        'avg_buy_price' => '0',
    ]);

    $order = assetRiskOrder($trader, 10_000_000, 'buy');
    $result = app(MetalOrderAssetRiskService::class)->assess($order, $trader);

    expect($result['total_assets_toman'])->toBe(1_050_000.0)
        ->and($result['asset_ratio_percent'])->toBe(10.5)
        ->and($result['is_sufficient'])->toBeTrue();
});

it('returns null asset risk for non metal_trader orders', function () {
    $order = new MetalOrder([
        'created_type' => 'admin',
        'created_id' => 1,
        'amount' => 1000,
        'order_type' => 'buy',
    ]);

    $result = app(MetalOrderAssetRiskService::class)->assess($order);

    expect($result)->toBeNull();
});

it('flags withdrawal risk when remaining assets cannot cover commitment reserve', function () {
    $trader = assetRiskTrader(10);

    MetalTraderWallet::query()->create([
        'metal_trader_id' => $trader->id,
        'metal_item_id' => null,
        'available_balance' => '40000',
        'blocked_balance' => '0',
        'fiat_balance' => '0',
        'avg_buy_price' => '0',
    ]);

    MetalOrder::create([
        'tracking_code' => random_int(1000000000, 9999999999),
        'created_id' => $trader->id,
        'created_type' => 'metal_trader',
        'order_type' => 'buy',
        'status' => 'succeed',
        'settlement_status' => 'pending',
        'product_name' => 'commitment',
        'qty' => 1,
        'unit' => 'gram',
        'fee' => 1_000_000,
        'amount' => 1_000_000,
        'extra_data' => [],
    ]);

    $result = app(MetalOrderAssetRiskService::class)->assessWithdrawal($trader, 100_000);

    expect($result['total_assets_after_withdrawal'])->toBe(40000.0)
        ->and($result['open_commitment_amount'])->toBe(1000000.0)
        ->and($result['required_reserve_toman'])->toBe(100000.0)
        ->and($result['covers_reserve'])->toBeFalse()
        ->and($result['shortage_toman'])->toBe(60000.0);
});
