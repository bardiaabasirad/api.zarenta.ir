<?php

namespace App\Http\Controllers\api\v1\external;

use App\Constants\AppConstants;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClientPriceResource;
use App\Models\DealingGroup;
use App\Models\MarketPrice;
use App\Models\SelectedMetalPrice;
use App\Models\Setting;
use OpenApi\Attributes as OA;

class ClientMarketController extends Controller
{
    #[OA\Get(
        path: "/api/clients/price",
        summary: "دریافت لیست محصولات در دسترس با قیمت",
        security: [["X-API-Key" => []]],
        tags: ["Products"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(ref: "#/components/schemas/ProductPriceListResponse")
            ),
            new OA\Response(
                response: 401,
                description: "Unauthorized",
                content: new OA\JsonContent(ref: "#/components/schemas/Error401")
            ),
            new OA\Response(
                response: 403,
                description: "Forbidden",
                content: new OA\JsonContent(ref: "#/components/schemas/Error403")
            )
        ]
    )]
    public function price()
    {
        $marketStatus = Setting::where('option_key', 'market_status')->value('option_value');

        // 1) Early Return: اگر کل بازار غیرفعال باشد، وضعیت همه inactive است و هیچ آیتم فعالی نداریم
        if ($marketStatus === 'inactive') {
            return response()->json([]);
        }

        $metalTrader = request()->get('api_client');
        $metalTrader->load('dealingGroup.metalItemConfigs');

        if (!$metalTrader->dealingGroup) {
            $setting = Setting::firstWhere('option_key', 'default_metal_trader_group_id');
            if ($setting) {
                $defaultDealingGroup = DealingGroup::with('metalItemConfigs')->find($setting->option_value);
                if ($defaultDealingGroup) {
                    $metalTrader->setRelation('dealingGroup', $defaultDealingGroup);
                }
            }
        }

        // تبدیل metal_item_configs به یک Map بر اساس metal_item_id
        $configs = $metalTrader->dealingGroup?->metalItemConfigs?->keyBy('metal_item_id') ?? collect();

        $allowedMetalItemIds = $configs->keys();

        $latestPrices = SelectedMetalPrice::selectRaw('MAX(id) as id, metal_item_id')
            ->whereIn('metal_item_id', $allowedMetalItemIds)
            ->groupBy('metal_item_id');

        $data = SelectedMetalPrice::query()
            ->joinSub($latestPrices, 'latest', function ($join) {
                $join->on('selected_metal_prices.id', '=', 'latest.id');
            })
            ->join('metal_items', 'metal_items.id', '=', 'selected_metal_prices.metal_item_id')
            ->where('metal_items.is_visible', true) // فقط آیتم‌های مرئی
            ->where(function ($query) {
                // حداقل یکی از وضعیت‌های خرید یا فروش باید فعال باشد
                $query->where('metal_items.is_buy_active', true)
                    ->orWhere('metal_items.is_sell_active', true);
            })
            ->select(
                'metal_items.id as product_id',
                'metal_items.title as product_name',
                'metal_items.is_buy_active',
                'metal_items.is_sell_active',
                'metal_items.metal_item_group_id',
                'metal_items.sort_order',
                'selected_metal_prices.buy',
                'selected_metal_prices.sell',
                'selected_metal_prices.time',
            )
            ->orderBy('metal_items.metal_item_group_id')
            ->orderBy('metal_items.sort_order')
            ->get()
            ->map(function ($row) use ($configs) {
                $cfg = $configs[$row->product_id];

                //------------------------------------------------------
                // 1) اعمال tolerance_type
                //------------------------------------------------------
                if ($cfg->tolerance_type === 'fixed_amount') {
                    $buy  = $row->buy  + ($cfg->buy_fee_margin ?? 0);
                    $sell = $row->sell + ($cfg->sell_fee_margin ?? 0);

                } elseif ($cfg->tolerance_type === 'percentage') {
                    $buy  = $row->buy  + ($row->buy  * ($cfg->buy_fee_margin  ?? 0) / 100);
                    $sell = $row->sell + ($row->sell * ($cfg->sell_fee_margin ?? 0) / 100);

                } else {
                    $buy  = $row->buy;
                    $sell = $row->sell;
                }

                //------------------------------------------------------
                // 2) اعمال display_mode
                //------------------------------------------------------
                if ($cfg->display_mode === 'per_gram') {
                    $factor = AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR;
                    $buy  = roundDownToThousand($buy  / $factor);
                    $sell = roundUpToThousand($sell / $factor);
                }

                //------------------------------------------------------
                // 3) تعیین وضعیت وضعیت خرید و فروش
                //------------------------------------------------------
                $buyStatus  = $row->is_buy_active  ? 'active' : 'inactive';
                $sellStatus = $row->is_sell_active ? 'active' : 'inactive';

                //------------------------------------------------------
                // خروجی نهایی
                //------------------------------------------------------
                return [
                    'product_id'   => $row->product_id,
                    'product_name' => $row->product_name,
                    'buy'          => round($sell),
                    'sell'         => round($buy),
                    'buy_status'   => $buyStatus,
                    'sell_status'  => $sellStatus,
                    'time'         => $row->time,
                ];
            });

        return response()->json($data->values());
    }

    #[OA\Get(
        path: "/api/clients/market",
        summary: "دریافت قیمت‌های بازار",
        security: [["X-API-Key" => []]],
        tags: ["Market"],
        responses: [
            new OA\Response(
                response: 200,
                description: "آخرین قیمت بازار",
                content: new OA\JsonContent(ref: "#/components/schemas/MarketPrice")
            ),
            new OA\Response(
                response: 401,
                description: "Unauthorized",
                content: new OA\JsonContent(ref: "#/components/schemas/Error401")
            ),
            new OA\Response(
                response: 403,
                description: "Forbidden",
                content: new OA\JsonContent(ref: "#/components/schemas/Error403")
            )
        ]
    )]
    public function market()
    {
        // آخرین نرخ تابان گوهر نفیس
        return response()->json(new ClientPriceResource(MarketPrice::latest()->first()));
    }
}
