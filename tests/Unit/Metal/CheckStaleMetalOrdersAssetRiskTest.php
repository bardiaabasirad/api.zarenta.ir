<?php

use App\Actions\MetalOrder\CheckStaleMetalOrdersAction;
use App\Models\MetalOrder;
use App\Models\MetalTrader;
use App\Models\MetalTraderWallet;
use App\Models\Setting;
use App\Services\Hamtala\HamtalaOrderExchangeService;
use App\Services\Metal\MetalOrderAssetRiskService;
use Carbon\Carbon;

beforeEach(function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Database driver unavailable: ' . $e->getMessage());
    }

    Setting::query()->updateOrCreate(
        ['option_key' => 'auto_order_dispatch_enabled'],
        ['option_name' => 'auto_order_dispatch_enabled', 'option_value' => '1']
    );

    Setting::query()->updateOrCreate(
        ['option_key' => 'manual_order_review_duration_seconds'],
        ['option_name' => 'manual_order_review_duration_seconds', 'option_value' => '1']
    );
});

it('auto rejects stale metal_trader order when asset ratio is below threshold', function () {
    $trader = MetalTrader::query()->create([
        'name' => 'stale-risk-' . uniqid(),
        'phone' => (int) ('9' . random_int(100000000, 999999999)),
        'status' => 'active',
        'asset_risk_threshold' => 10,
    ]);

    MetalTraderWallet::query()->create([
        'metal_trader_id' => $trader->id,
        'metal_item_id' => null,
        'available_balance' => '1000',
        'blocked_balance' => '0',
        'fiat_balance' => '0',
        'avg_buy_price' => '0',
    ]);

    $order = MetalOrder::create([
        'tracking_code' => random_int(1000000000, 9999999999),
        'created_id' => $trader->id,
        'created_type' => 'metal_trader',
        'order_type' => 'buy',
        'status' => 'pending',
        'product_name' => 'test',
        'qty' => 1,
        'unit' => 'gram',
        'fee' => 1_000_000,
        'amount' => 1_000_000,
        'extra_data' => [],
        'created_at' => Carbon::now()->subSeconds(30),
        'updated_at' => Carbon::now()->subSeconds(30),
    ]);

    $hamtala = \Mockery::mock(HamtalaOrderExchangeService::class);
    $hamtala->shouldReceive('isWeightOutOfRange')->andReturn(false);
    $hamtala->shouldReceive('isAutoOrderDisabled')->andReturn(false);
    $hamtala->shouldNotReceive('submitAutoOrder');

    $action = new CheckStaleMetalOrdersAction($hamtala, app(MetalOrderAssetRiskService::class));
    $action->execute();

    $order->refresh();

    expect($order->status)->toBe('rejected')
        ->and($order->extra_data['cause'] ?? null)->toBe('insufficient_asset_ratio')
        ->and($order->extra_data['message'] ?? null)->toBe('کمبود نسبت دارایی به مبلغ سفارش')
        ->and($order->extra_data['asset_risk']['is_sufficient'] ?? true)->toBeFalse();
});
