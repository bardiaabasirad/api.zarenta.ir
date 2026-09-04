<?php

namespace App\Http\Controllers\api\v1\general;

use App\Enums\PersianCoinStatus;
use App\Enums\SectionStatus;
use App\Http\Controllers\Controller;
use App\Models\BoardCoin;
use App\Models\Directory;
use App\Models\MarketPrice;
use App\Models\PersianCoin;
use App\Models\Product;
use App\Models\PriceSource;
use App\Models\Section;
use App\Models\Setting;
use App\Models\RawMetalPrice;
use App\Services\BoardCoinService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

class LandingPageController extends Controller
{
    public function home()
    {
        // Assuming you have a single market price for simplicity
        $marketPrice = MarketPrice::latest()->first();
        $vat = Setting::where('option_key','value_added_tax')->firstOrFail()->option_value;
        $sections = Section::where('status', SectionStatus::ACTIVE)->with('sectionable')->orderBy('order')->get();

        foreach ($sections as $section){
            switch ($section->sectionable_type){
                case 'App\\Models\\Widget':
                    if ($section->sectionable->type === 'last_products'){
                        $section->data = Product::active()->latest()
                            ->whereHas('variety', function($query){
                                $query->where('count', '>', 0);
                            })
                            ->with(['variety' => function($query) use ($vat, $marketPrice) {
                                $query
                                    ->where('count', '>', 0)
                                    ->with(['color','images'])
                                    ->join('products', 'varieties.product_id', '=', 'products.id')
                                    ->addSelect([
                                        'varieties.*',
                                        'count',
                                        // Add your final_price calculation here as a select statement
                                        DB::raw("
                                            CASE
                                                WHEN varieties.count > 0 THEN
                                                    CEIL(
                                                        (
                                                            -- Initial value
                                                            ({$marketPrice->price} * varieties.weight) +
                                                            -- Sell wage
                                                            (
                                                                ({$marketPrice->price} * varieties.weight) *
                                                                (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                COALESCE(varieties.tomans_sell_wage, 0)
                                                            ) +
                                                            -- Profit
                                                            (
                                                                (
                                                                    ({$marketPrice->price} * varieties.weight) +
                                                                    (({$marketPrice->price} * varieties.weight) *
                                                                    (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                    COALESCE(varieties.tomans_sell_wage, 0))
                                                                ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                COALESCE(varieties.tomans_profit, 0)
                                                            ) +
                                                            -- VAT (only if product.vat is active)
                                                            CASE
                                                                WHEN products.vat = 'active' THEN
                                                                    (
                                                                        (
                                                                            (({$marketPrice->price} * varieties.weight) *
                                                                            (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                            COALESCE(varieties.tomans_sell_wage, 0)) +
                                                                            (
                                                                                (
                                                                                    ({$marketPrice->price} * varieties.weight) +
                                                                                    (({$marketPrice->price} * varieties.weight) *
                                                                                    (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                                    COALESCE(varieties.tomans_sell_wage, 0))
                                                                                ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                                COALESCE(varieties.tomans_profit, 0)
                                                                            )
                                                                        ) * {$vat}
                                                                    )
                                                                ELSE 0
                                                            END
                                                        ) * (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                        COALESCE(varieties.tomans_discount, 0)
                                                    )
                                                ELSE NULL
                                            END as final_price
                                        "),
                                    ])
                                    ->orderBy('final_price', 'asc');
                            }])
                            ->limit(16)->get();
                    }
                    break;
                case 'App\\Models\\Category':

                    if ($section->show_only_available_products){
                        $section->data = Product::active()
                            ->whereHas('variety', function($query){
                                $query->where('count', '>', 0);
                            })
                            ->whereHas('categories', function ($query) use ($section) {
                                $query->where('category_id', $section->sectionable_id);
                            })
                            ->with(['variety' => function($query) use ($vat, $marketPrice) {
                                $query
                                    ->where('count', '>', 0)
                                    ->with(['color','images'])
                                    ->join('products', 'varieties.product_id', '=', 'products.id')
                                    ->addSelect([
                                        'varieties.*',
                                        'count',
                                        // Add your final_price calculation here as a select statement
                                        DB::raw("
                                            CASE
                                                WHEN varieties.count > 0 THEN
                                                    CEIL(
                                                        (
                                                            -- Initial value
                                                            ({$marketPrice->price} * varieties.weight) +
                                                            -- Sell wage
                                                            (
                                                                ({$marketPrice->price} * varieties.weight) *
                                                                (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                COALESCE(varieties.tomans_sell_wage, 0)
                                                            ) +
                                                            -- Profit
                                                            (
                                                                (
                                                                    ({$marketPrice->price} * varieties.weight) +
                                                                    (({$marketPrice->price} * varieties.weight) *
                                                                    (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                    COALESCE(varieties.tomans_sell_wage, 0))
                                                                ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                COALESCE(varieties.tomans_profit, 0)
                                                            ) +
                                                            -- VAT (only if product.vat is active)
                                                            CASE
                                                                WHEN products.vat = 'active' THEN
                                                                    (
                                                                        (
                                                                            (({$marketPrice->price} * varieties.weight) *
                                                                            (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                            COALESCE(varieties.tomans_sell_wage, 0)) +
                                                                            (
                                                                                (
                                                                                    ({$marketPrice->price} * varieties.weight) +
                                                                                    (({$marketPrice->price} * varieties.weight) *
                                                                                    (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                                    COALESCE(varieties.tomans_sell_wage, 0))
                                                                                ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                                COALESCE(varieties.tomans_profit, 0)
                                                                            )
                                                                        ) * {$vat}
                                                                    )
                                                                ELSE 0
                                                            END
                                                        ) * (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                        COALESCE(varieties.tomans_discount, 0)
                                                    )
                                                ELSE NULL
                                            END as final_price
                                        "),
                                    ])
                                    ->orderBy('final_price', 'asc');
                            }])
                            ->limit(16)->get();
                    }
                    else{
                        $section->data = Product::active()
                            ->whereHas('categories', function ($query) use ($section) {
                                $query->where('category_id', $section->sectionable_id);
                            })
                            ->with([
                                'variety' => function($query) use ($vat, $marketPrice) {
                                    $query
                                        ->where('count', '>', 0)
                                        ->with(['color','images'])
                                        ->join('products', 'varieties.product_id', '=', 'products.id')
                                        ->addSelect([
                                            'varieties.*',
                                            'count',
                                            // Add your final_price calculation here as a select statement
                                            DB::raw("
                                                CASE
                                                    WHEN varieties.count > 0 THEN
                                                        CEIL(
                                                            (
                                                                -- Initial value
                                                                ({$marketPrice->price} * varieties.weight) +
                                                                -- Sell wage
                                                                (
                                                                    ({$marketPrice->price} * varieties.weight) *
                                                                    (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                    COALESCE(varieties.tomans_sell_wage, 0)
                                                                ) +
                                                                -- Profit
                                                                (
                                                                    (
                                                                        ({$marketPrice->price} * varieties.weight) +
                                                                        (({$marketPrice->price} * varieties.weight) *
                                                                        (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                        COALESCE(varieties.tomans_sell_wage, 0))
                                                                    ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                    COALESCE(varieties.tomans_profit, 0)
                                                                ) +
                                                                -- VAT (only if product.vat is active)
                                                                CASE
                                                                    WHEN products.vat = 'active' THEN
                                                                        (
                                                                            (
                                                                                (({$marketPrice->price} * varieties.weight) *
                                                                                (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                                COALESCE(varieties.tomans_sell_wage, 0)) +
                                                                                (
                                                                                    (
                                                                                        ({$marketPrice->price} * varieties.weight) +
                                                                                        (({$marketPrice->price} * varieties.weight) *
                                                                                        (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                                        COALESCE(varieties.tomans_sell_wage, 0))
                                                                                    ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                                    COALESCE(varieties.tomans_profit, 0)
                                                                                )
                                                                            ) * {$vat}
                                                                        )
                                                                    ELSE 0
                                                                END
                                                            ) * (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                            COALESCE(varieties.tomans_discount, 0)
                                                        )
                                                    ELSE NULL
                                                END as final_price
                                            "),
                                        ])
                                        ->orderBy('final_price', 'asc');
                                },
                                'imageVariety' => function($query) use ($vat, $marketPrice) {
                                    $query
                                        ->with(['images'])
                                        ->join('products', 'varieties.product_id', '=', 'products.id')
                                        ->addSelect([
                                            'varieties.*',
                                            'count',
                                            // Add your final_price calculation here as a select statement
                                            DB::raw("
                                                CASE
                                                    WHEN varieties.count > 0 THEN
                                                        CEIL(
                                                            (
                                                                -- Initial value
                                                                ({$marketPrice->price} * varieties.weight) +
                                                                -- Sell wage
                                                                (
                                                                    ({$marketPrice->price} * varieties.weight) *
                                                                    (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                    COALESCE(varieties.tomans_sell_wage, 0)
                                                                ) +
                                                                -- Profit
                                                                (
                                                                    (
                                                                        ({$marketPrice->price} * varieties.weight) +
                                                                        (({$marketPrice->price} * varieties.weight) *
                                                                        (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                        COALESCE(varieties.tomans_sell_wage, 0))
                                                                    ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                    COALESCE(varieties.tomans_profit, 0)
                                                                ) +
                                                                -- VAT (only if product.vat is active)
                                                                CASE
                                                                    WHEN products.vat = 'active' THEN
                                                                        (
                                                                            (
                                                                                (({$marketPrice->price} * varieties.weight) *
                                                                                (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                                COALESCE(varieties.tomans_sell_wage, 0)) +
                                                                                (
                                                                                    (
                                                                                        ({$marketPrice->price} * varieties.weight) +
                                                                                        (({$marketPrice->price} * varieties.weight) *
                                                                                        (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                                        COALESCE(varieties.tomans_sell_wage, 0))
                                                                                    ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                                    COALESCE(varieties.tomans_profit, 0)
                                                                                )
                                                                            ) * {$vat}
                                                                        )
                                                                    ELSE 0
                                                                END
                                                            ) * (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                            COALESCE(varieties.tomans_discount, 0)
                                                        )
                                                    ELSE NULL
                                                END as final_price
                                            "),
                                        ])
                                        ->orderBy('final_price', 'asc');
                                }
                            ])
                            ->limit(16)->get();
                    }
                    break;
                case 'App\\Models\\Directory':
                    $directory = Directory::find($section->sectionable_id);

                    if ($section->show_only_available_products) {
                        $section->data = $directory->products()->active()
                            ->whereHas('variety', function($query){
                                $query->where('count', '>', 0);
                            })
                            ->withPivot('order') // Eager load the 'order' column from the pivot table
                            ->select('id','title','created_at','vat')
                            ->with(['variety' => function($query) use ($vat, $marketPrice) {
                                $query
                                    ->where('count', '>', 0)
                                    ->with(['color','images'])
                                    ->join('products', 'varieties.product_id', '=', 'products.id')
                                    ->addSelect([
                                        'varieties.*',
                                        'count',
                                        // Add your final_price calculation here as a select statement
                                        DB::raw("
                                            CASE
                                                WHEN varieties.count > 0 THEN
                                                    CEIL(
                                                        (
                                                            -- Initial value
                                                            ({$marketPrice->price} * varieties.weight) +
                                                            -- Sell wage
                                                            (
                                                                ({$marketPrice->price} * varieties.weight) *
                                                                (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                COALESCE(varieties.tomans_sell_wage, 0)
                                                            ) +
                                                            -- Profit
                                                            (
                                                                (
                                                                    ({$marketPrice->price} * varieties.weight) +
                                                                    (({$marketPrice->price} * varieties.weight) *
                                                                    (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                    COALESCE(varieties.tomans_sell_wage, 0))
                                                                ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                COALESCE(varieties.tomans_profit, 0)
                                                            ) +
                                                            -- VAT (only if product.vat is active)
                                                            CASE
                                                                WHEN products.vat = 'active' THEN
                                                                    (
                                                                        (
                                                                            (({$marketPrice->price} * varieties.weight) *
                                                                            (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                            COALESCE(varieties.tomans_sell_wage, 0)) +
                                                                            (
                                                                                (
                                                                                    ({$marketPrice->price} * varieties.weight) +
                                                                                    (({$marketPrice->price} * varieties.weight) *
                                                                                    (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                                    COALESCE(varieties.tomans_sell_wage, 0))
                                                                                ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                                COALESCE(varieties.tomans_profit, 0)
                                                                            )
                                                                        ) * {$vat}
                                                                    )
                                                                ELSE 0
                                                            END
                                                        ) * (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                        COALESCE(varieties.tomans_discount, 0)
                                                    )
                                                ELSE NULL
                                            END as final_price
                                        "),
                                    ])
                                    ->orderBy('final_price', 'asc');
                            }])
                            ->orderByPivot('order', 'DESC')
                            ->limit(16)->get();
                    }
                    else {
                        $section->data = $directory->products()->active()
                            ->withPivot('order') // Eager load the 'order' column from the pivot table
                            ->select('id','title','created_at','vat')
                            ->with([
                                'variety' => function($query) use ($vat, $marketPrice) {
                                    $query
                                        ->where('count', '>', 0)
                                        ->with(['color','images'])
                                        ->join('products', 'varieties.product_id', '=', 'products.id')
                                        ->addSelect([
                                            'varieties.*',
                                            'count',
                                            // Add your final_price calculation here as a select statement
                                            DB::raw("
                                                CASE
                                                    WHEN varieties.count > 0 THEN
                                                        CEIL(
                                                            (
                                                                -- Initial value
                                                                ({$marketPrice->price} * varieties.weight) +
                                                                -- Sell wage
                                                                (
                                                                    ({$marketPrice->price} * varieties.weight) *
                                                                    (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                    COALESCE(varieties.tomans_sell_wage, 0)
                                                                ) +
                                                                -- Profit
                                                                (
                                                                    (
                                                                        ({$marketPrice->price} * varieties.weight) +
                                                                        (({$marketPrice->price} * varieties.weight) *
                                                                        (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                        COALESCE(varieties.tomans_sell_wage, 0))
                                                                    ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                    COALESCE(varieties.tomans_profit, 0)
                                                                ) +
                                                                -- VAT (only if product.vat is active)
                                                                CASE
                                                                    WHEN products.vat = 'active' THEN
                                                                        (
                                                                            (
                                                                                (({$marketPrice->price} * varieties.weight) *
                                                                                (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                                COALESCE(varieties.tomans_sell_wage, 0)) +
                                                                                (
                                                                                    (
                                                                                        ({$marketPrice->price} * varieties.weight) +
                                                                                        (({$marketPrice->price} * varieties.weight) *
                                                                                        (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                                        COALESCE(varieties.tomans_sell_wage, 0))
                                                                                    ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                                    COALESCE(varieties.tomans_profit, 0)
                                                                                )
                                                                            ) * {$vat}
                                                                        )
                                                                    ELSE 0
                                                                END
                                                            ) * (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                            COALESCE(varieties.tomans_discount, 0)
                                                        )
                                                    ELSE NULL
                                                END as final_price
                                            "),
                                        ])
                                        ->orderBy('final_price', 'asc');
                                },
                                'imageVariety' => function($query) use ($vat, $marketPrice) {
                                    $query
                                        ->with(['images'])
                                        ->join('products', 'varieties.product_id', '=', 'products.id')
                                        ->addSelect([
                                            'varieties.*',
                                            'count',
                                            // Add your final_price calculation here as a select statement
                                            DB::raw("
                                                CASE
                                                    WHEN varieties.count > 0 THEN
                                                        CEIL(
                                                            (
                                                                -- Initial value
                                                                ({$marketPrice->price} * varieties.weight) +
                                                                -- Sell wage
                                                                (
                                                                    ({$marketPrice->price} * varieties.weight) *
                                                                    (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                    COALESCE(varieties.tomans_sell_wage, 0)
                                                                ) +
                                                                -- Profit
                                                                (
                                                                    (
                                                                        ({$marketPrice->price} * varieties.weight) +
                                                                        (({$marketPrice->price} * varieties.weight) *
                                                                        (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                        COALESCE(varieties.tomans_sell_wage, 0))
                                                                    ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                    COALESCE(varieties.tomans_profit, 0)
                                                                ) +
                                                                -- VAT (only if product.vat is active)
                                                                CASE
                                                                    WHEN products.vat = 'active' THEN
                                                                        (
                                                                            (
                                                                                (({$marketPrice->price} * varieties.weight) *
                                                                                (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                                COALESCE(varieties.tomans_sell_wage, 0)) +
                                                                                (
                                                                                    (
                                                                                        ({$marketPrice->price} * varieties.weight) +
                                                                                        (({$marketPrice->price} * varieties.weight) *
                                                                                        (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                                        COALESCE(varieties.tomans_sell_wage, 0))
                                                                                    ) * (COALESCE(varieties.percentage_profit, 0) / 100) +
                                                                                    COALESCE(varieties.tomans_profit, 0)
                                                                                )
                                                                            ) * {$vat}
                                                                        )
                                                                    ELSE 0
                                                                END
                                                            ) * (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                            COALESCE(varieties.tomans_discount, 0)
                                                        )
                                                    ELSE NULL
                                                END as final_price
                                            "),
                                        ])
                                        ->orderBy('final_price', 'asc');
                                }
                            ])
                            ->orderByPivot('order', 'DESC')
                            ->limit(16)->get();
                    }

                    break;
            }
        }

        return response()->json([
            'sections' => $sections,
        ]);
    }

    public function board()
    {
        $coins = BoardCoinService::getCoinsWithBuyAndSellPrices();

        return response()->json([
            'persian_coins' => PersianCoin::where('status', PersianCoinStatus::ACTIVE)->orderBy('weight')->get(),
            'coins' => $coins,
        ]);
    }

    public function coins()
    {
        $coins = BoardCoinService::getCoinsWithBuyAndSellPrices();

        return response()->json([
            'coins' => $coins,
            'market_status' => Setting::where('option_key', 'market_status')->first()->option_value,
        ]);
    }

    public function domesticMarketLanding()
    {
        $settings = Setting::whereIn('option_key',[
            'common_fixed_price_buy',
            'common_percentage_price_buy',
            'common_fixed_price_sell',
            'common_percentage_price_sell',
            'today_fixed_price_buy',
            'today_percentage_price_buy',
            'today_fixed_price_sell',
            'today_percentage_price_sell',
            'tomorrow_fixed_price_buy',
            'tomorrow_percentage_price_buy',
            'tomorrow_fixed_price_sell',
            'tomorrow_percentage_price_sell',
            'reference_channel_id',
        ])->get()->keyBy('option_key');

        $channel = PriceSource::find($settings['reference_channel_id']->option_value);
        $rate = RawMetalPrice::latest()->where('reference_channel_id', $channel->id)->first();
        $yesterday_rate = RawMetalPrice::where('reference_channel_id', $channel->id)->whereDate('created_at', Carbon::yesterday())->latest()->first();

        $now = Jalalian::fromFormat('Y-m-d H:i:s', Jalalian::now())->toCarbon()->endOfDay();
        $yesterday = Jalalian::fromFormat('Y-m-d H:i:s', Jalalian::now()->subDay())->toCarbon()->startOfDay();

        $chartData = RawMetalPrice::where('reference_channel_id', $channel->id)
            ->select('id', 'buy', 'sell', 'time')
            ->whereBetween('created_at', [$yesterday, $now])
            ->limit(20)
            ->get();

        $xAxis = [];
        $yAxisFirst = [];
        $yAxisSecond = [];
        foreach ($chartData as $data){
            $xAxis[] = Carbon::parse($data->time)->format('H:i:s');
            $yAxisFirst[] = $data->sell;
            $yAxisSecond[] = $data->buy;
        }

        return response()->json([
            'rate' => $rate,
            'yesterday_rate' => $yesterday_rate,
            'common_fixed_price_buy' => $settings['common_fixed_price_buy']->option_value,
            'common_percentage_price_buy' => $settings['common_percentage_price_buy']->option_value,
            'common_fixed_price_sell' => $settings['common_fixed_price_sell']->option_value,
            'common_percentage_price_sell' => $settings['common_percentage_price_sell']->option_value,
            'today_fixed_price_buy' => $settings['today_fixed_price_buy']->option_value,
            'today_percentage_price_buy' => $settings['today_percentage_price_buy']->option_value,
            'today_fixed_price_sell' => $settings['today_fixed_price_sell']->option_value,
            'today_percentage_price_sell' => $settings['today_percentage_price_sell']->option_value,
            'tomorrow_fixed_price_buy' => $settings['tomorrow_fixed_price_buy']->option_value,
            'tomorrow_percentage_price_buy' => $settings['tomorrow_percentage_price_buy']->option_value,
            'tomorrow_fixed_price_sell' => $settings['tomorrow_fixed_price_sell']->option_value,
            'tomorrow_percentage_price_sell' => $settings['tomorrow_percentage_price_sell']->option_value,
            'x_axis' => $xAxis,
            'y_axis_first' => $yAxisFirst,
            'y_axis_second' => $yAxisSecond,
        ]);
    }
}
