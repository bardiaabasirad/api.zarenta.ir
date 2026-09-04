<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductShowGeneralReasource;
use App\Models\Category;
use App\Models\Color;
use App\Models\MarketPrice;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SortOptions;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function autocomplete()
    {
        $marketPrice = MarketPrice::latest()->first();
        $vat = Setting::where('option_key','value_added_tax')->firstOrFail()->option_value;

        $value = request()->input('q');

        $products = Product::active()->latest()
            ->whereHas('variety', function($query){
                $query->where('count', '>', 0)->orderBy('weight');
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
            ->when($value, function($query) use ($value) {
                return $query->where('title', 'like', "%{$value}%");
            })
            ->limit(10)
            ->get();

        return response()->json($products);
    }

    public function search()
    {
        // Assuming you have a single market price for simplicity
        $marketPrice = MarketPrice::latest()->first();
        $vat = Setting::where('option_key','value_added_tax')->firstOrFail()->option_value;

        $count = request()->input('count');
        $sort = request()->input('sort');
        $q = request()->input('q');
        $weight_from = request()->input('weight_from');
        $weight_to = request()->input('weight_to');
        $price_from = request()->input('price_from');
        $price_to = request()->input('price_to');
        $wage_from = request()->input('wage_from');
        $wage_to = request()->input('wage_to');
        $products = Product::query()->active();

        $products = $products
            ->when($q, function($query) use ($q){
                return $query->where('title', 'like', "%{$q}%");
            })
            ->when(request()->filled('has_selling_stock'), function($query){
                return $query->whereHas('varieties', function($query){
                    $query->where('count', '>', 0);
                });
            })
            ->when(request()->filled('colors'), function($query){
                return $query->whereHas('variety', function($query){
                    $query->whereIn('color_id', request()->input('colors'));
                });
            })
            ->when(request()->filled('categories'), function($query){
                return $query->whereHas('categories', function($query){
                    $query->whereIn('category_id', request()->input('categories'));
                });
            })
            ->when(request()->filled('weight_from') || request()->filled('weight_to'), function($query){
                $weight_from = request('weight_from') ?: 0; // Default to 0 if not provided
                $weight_to = request('weight_to') ?: PHP_INT_MAX; // Default to PHP_INT_MAX if not provided

                return $query->whereHas('varieties', function($query) use ($weight_to, $weight_from) {
                    $query->whereBetween('varieties.weight', [$weight_from, $weight_to]);
                });
            })
            /**این شرط برای خود محصول است نه تنوع‌های آن**/
            /**اگر محصولی بین قیمت انتخابی نباشد کلا محصول را برگشت نده**/
            ->when(request()->filled('price_from') || request()->filled('price_to'), function($query) use ($vat, $marketPrice) {
                $price_from = request('price_from', 0);
                $price_to = request('price_to', PHP_INT_MAX);

                return $query->whereHas('varieties', function ($query) use ($vat, $marketPrice, $price_from, $price_to) {
                    $query->where('count', '>', 0)
                        ->select(
                            'varieties.*',
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
                            ")
                        )
                        ->havingRaw('final_price BETWEEN ? AND ?', [$price_from, $price_to]);
                });
            })
            /**این شرط برای خود محصول است نه تنوع‌های آن**/
            /**اگر محصولی بین اجرت انتخابی نباشد کلا محصول را برگشت نده**/
            ->when(request()->filled('wage_from') || request()->filled('wage_to'), function($query) use ($marketPrice) {
                $wage_from = request()->input('wage_from', 0);
                $wage_to = request()->input('wage_to', PHP_INT_MAX);

                return $query->whereHas('variety', function ($query) use ($marketPrice, $wage_from, $wage_to) {
                    $query->select(
                        'varieties.*',
                        DB::raw("
                            FLOOR(
                                (
                                    (
                                        $marketPrice->price * (COALESCE(percentage_sell_wage, 0) / 100) +
                                        (COALESCE(tomans_sell_wage, 0))
                                    ) /
                                    (
                                        $marketPrice->price +
                                        (
                                            $marketPrice->price *
                                            (COALESCE(percentage_sell_wage, 0) / 100) +
                                            (COALESCE(tomans_sell_wage, 0))
                                        )
                                    )
                                ) * 100
                            ) as sell_wage"
                        )
                    )
                    ->where('count', '>', 0)
                    ->havingRaw('sell_wage >= ? AND sell_wage <= ?', [$wage_from, $wage_to]);
                });
            })
            /**این شرط برای خود محصول است نه تنوع‌های آن**/
            /**اگر محصولی بین بازه وزن انتخابی نباشد کلا محصول را برگشت نده**/
            ->when(request()->filled('weight_from') || request()->filled('weight_to'), function($query) {
                $weight_from = request()->input('weight_from', 0); // Default to 0 if not provided
                $weight_to = request()->input('weight_to', PHP_INT_MAX); // Default to PHP_INT_MAX if not provided

                return $query->whereHas('variety', function ($query) use ($weight_from, $weight_to) {
                    $query->whereBetween('weight', [$weight_from, $weight_to]);
                });
            })
            /**مرتب سازی نتایج**/
            ->when($sort, function($query) use ($vat, $wage_to, $wage_from, $price_from, $price_to, $weight_from, $weight_to, $sort, $marketPrice) {

                $weight_from = $weight_from ?: 0; // Default to 0 if not provided
                $weight_to = $weight_to ?: PHP_INT_MAX; // Default to PHP_INT_MAX if not provided
                $price_from = $price_from ?: 0; // Default to 0 if not provided
                $price_to = $price_to ?: PHP_INT_MAX; // Default to PHP_INT_MAX if not provided
                $wage_from = $wage_from ?: 0; // Default to 0 if not provided
                $wage_to = $wage_to ?: PHP_INT_MAX; // Default to PHP_INT_MAX if not provided

                switch ($sort){
                    case 1: // جدیدترین
                        return $query->joinSub(function ($query) use ($vat, $wage_to, $wage_from, $price_to, $price_from, $weight_to, $weight_from, $marketPrice) {
                            return $query->from('varieties')
                                ->join('products', 'varieties.product_id', '=', 'products.id')
                                ->select(
                                    'product_id',
                                    DB::raw("
                                        MIN(
                                            CASE
                                                WHEN count <= 0 THEN NULL
                                                WHEN weight BETWEEN $weight_from AND $weight_to
                                                AND (
                                                    FLOOR(
                                                        (
                                                            (
                                                                $marketPrice->price * (COALESCE(percentage_sell_wage, 0) / 100) +
                                                                (COALESCE(tomans_sell_wage, 0))
                                                            ) /
                                                            (
                                                                $marketPrice->price +
                                                                (
                                                                    $marketPrice->price *
                                                                    (COALESCE(percentage_sell_wage, 0) / 100) +
                                                                    (COALESCE(tomans_sell_wage, 0))
                                                                )
                                                            )
                                                        ) * 100
                                                    )
                                                ) BETWEEN $wage_from AND $wage_to -- check sell_price in range
                                                AND (
                                                    SELECT ROUND(
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
                                                        ) *
                                                        (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                        COALESCE(varieties.tomans_discount, 0)
                                                    )
                                                ) BETWEEN $price_from AND $price_to -- check sell_price in range
                                                THEN (
                                                    SELECT ROUND (
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
                                                        ) *
                                                        (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                        COALESCE(varieties.tomans_discount, 0)
                                                    )
                                                )
                                                ELSE 0
                                            END) as final_price"))
                                ->groupBy('product_id');
                        }, 'varieties', 'products.id', '=', 'varieties.product_id')
                            ->orderBy('products.created_at', 'DESC') // Added to sort by created_at
                            ->orderByRaw('final_price ASC');
                    case 2: // ارزانترین
                        return $query->joinSub(function ($query) use ($vat, $wage_to, $wage_from, $price_to, $price_from, $weight_to, $weight_from, $marketPrice) {
                            return $query->from('varieties')
                                ->join('products', 'varieties.product_id', '=', 'products.id')
                                ->select(
                                    'product_id',
                                    DB::raw("
                                        MIN(
                                            CASE
                                            WHEN count <= 0 THEN NULL
                                            WHEN weight BETWEEN $weight_from AND $weight_to
                                            AND (
                                                FLOOR(
                                                    (
                                                        (
                                                            $marketPrice->price * (COALESCE(percentage_sell_wage, 0) / 100) +
                                                            (COALESCE(tomans_sell_wage, 0))
                                                        ) /
                                                        (
                                                            $marketPrice->price +
                                                            (
                                                                $marketPrice->price *
                                                                (COALESCE(percentage_sell_wage, 0) / 100) +
                                                                (COALESCE(tomans_sell_wage, 0))
                                                            )
                                                        )
                                                    ) * 100
                                                )
                                            ) BETWEEN $wage_from AND $wage_to -- check sell_price in range
                                            AND (
                                                SELECT ROUND(
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
                                                    ) *
                                                    (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                    COALESCE(varieties.tomans_discount, 0)
                                                )
                                            ) BETWEEN $price_from AND $price_to -- check sell_price in range
                                            THEN (
                                                SELECT ROUND (
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
                                                    ) *
                                                    (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                    COALESCE(varieties.tomans_discount, 0)
                                                )
                                            )
                                            ELSE NULL
                                        END) as final_price"
                                    )
                                )
                                ->groupBy('product_id');
                        }, 'varieties', 'products.id', '=', 'varieties.product_id')
                            ->orderByRaw('ISNULL(final_price) ASC, final_price ASC');
                    case 3: // گرانترین
                        return $query->joinSub(function ($query) use ($vat, $wage_to, $wage_from, $price_to, $price_from, $weight_to, $weight_from, $marketPrice) {
                            return $query->from('varieties')
                                ->join('products', 'varieties.product_id', '=', 'products.id')
                                ->select(
                                    'product_id',
                                    DB::raw("
                                        MAX(
                                            CASE
                                                WHEN count <= 0 THEN NULL
                                                WHEN weight BETWEEN $weight_from AND $weight_to
                                                AND (
                                                    FLOOR(
                                                    (
                                                        (
                                                            $marketPrice->price * (COALESCE(percentage_sell_wage, 0) / 100) +
                                                            (COALESCE(tomans_sell_wage, 0))
                                                        ) /
                                                        (
                                                            $marketPrice->price +
                                                            (
                                                                $marketPrice->price *
                                                                (COALESCE(percentage_sell_wage, 0) / 100) +
                                                                (COALESCE(tomans_sell_wage, 0))
                                                            )
                                                        )
                                                    ) * 100
                                                )
                                                ) BETWEEN $wage_from AND $wage_to -- check sell_price in range
                                                AND (
                                                    SELECT ROUND(
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
                                                    ) *
                                                    (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                    COALESCE(varieties.tomans_discount, 0)
                                                )
                                                ) BETWEEN $price_from AND $price_to -- check sell_price in range
                                                THEN (
                                                    SELECT ROUND (
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
                                                        ) *
                                                        (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                        COALESCE(varieties.tomans_discount, 0)
                                                    )
                                                )
                                                ELSE NULL
                                        END) as final_price
                                    ")
                                )
                                ->groupBy('product_id');
                        }, 'varieties', 'products.id', '=', 'varieties.product_id')
                            ->orderByRaw('ISNULL(final_price) ASC, final_price DESC');
                    case 4: // کم اجرت ترین
                        return $query->joinSub(function ($query) use ($vat, $price_to, $price_from, $wage_to, $wage_from, $weight_to, $weight_from, $marketPrice) {
                            return $query->from('varieties')
                                ->join('products', 'varieties.product_id', '=', 'products.id')
                                ->select(
                                    'product_id',
                                    DB::raw("
                                        MIN(
                                            CASE
                                            WHEN count <= 0 THEN NULL
                                            WHEN weight BETWEEN $weight_from AND $weight_to
                                            AND (
                                                FLOOR(
                                                    (
                                                        (
                                                            $marketPrice->price * (COALESCE(percentage_sell_wage, 0) / 100) +
                                                            (COALESCE(tomans_sell_wage, 0))
                                                        ) /
                                                        (
                                                            $marketPrice->price +
                                                            (
                                                                $marketPrice->price *
                                                                (COALESCE(percentage_sell_wage, 0) / 100) +
                                                                (COALESCE(tomans_sell_wage, 0))
                                                            )
                                                        )
                                                    ) * 100
                                                )
                                            ) BETWEEN $wage_from AND $wage_to -- check sell_price in range
                                            AND (
                                                SELECT ROUND(
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
                                                    ) *
                                                    (1 - COALESCE(varieties.percentage_discount, 0) / 100) -
                                                    COALESCE(varieties.tomans_discount, 0)
                                                )
                                            ) BETWEEN $price_from AND $price_to -- check sell_price in range
                                            THEN (
                                                SELECT FLOOR (
                                                    (
                                                        -- Sell wage
                                                        (
                                                            ({$marketPrice->price} * varieties.weight) *
                                                            (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                            COALESCE(varieties.tomans_sell_wage, 0)
                                                        )
                                                    ) /
                                                    (
                                                        -- Initial value + sell wage
                                                        (
                                                            ({$marketPrice->price} * varieties.weight) +
                                                            (
                                                                ({$marketPrice->price} * varieties.weight) *
                                                                (COALESCE(varieties.percentage_sell_wage, 0) / 100) +
                                                                COALESCE(varieties.tomans_sell_wage, 0)
                                                            )
                                                        )
                                                    ) * 100
                                                )
                                            )
                                            ELSE NULL
                                        END) AS sell_wage"
                                    )
                                )
                                ->groupBy('product_id');
                        }, 'varieties', 'products.id', '=', 'varieties.product_id')
                            ->orderByRaw('sell_wage ASC');
                }
            })
            /**برگشت دادن تنوع محصول بر اساس شرایط تعیین شده**/
            ->with([
                'variety' => function($query) use ($vat, $wage_to, $wage_from, $price_to, $price_from, $weight_to, $weight_from, $sort, $marketPrice) {
                    $query->where('count', '>', 0)
                        ->with(['color','images'])
                        ->when($weight_from || $weight_to, function ($query) use ($weight_from, $weight_to) {
                            // Apply the weight condition
                            $weight_from = $weight_from ?: 0; // Default to 0 if not provided
                            $weight_to = $weight_to ?: PHP_INT_MAX; // Default to PHP_INT_MAX if not provided
                            return $query->whereBetween('varieties.weight', [$weight_from, $weight_to]);
                        })
                        ->when($price_from || $price_to, function ($query) use ($price_from, $price_to) {
                            // Apply the price condition
                            $price_from = $price_from ?: 0; // Default to 0 if not provided
                            $price_to = $price_to ?: PHP_INT_MAX; // Default to PHP_INT_MAX if not provided
                            return $query->havingRaw('final_price BETWEEN ? AND ?', [$price_from, $price_to]);
                        })
                        ->when($wage_from || $wage_to, function ($query) use ($wage_from, $wage_to) {
                            // Apply the sell condition
                            $wage_from = $wage_from ?: 0; // Default to 0 if not provided
                            $wage_to = $wage_to ?: PHP_INT_MAX; // Default to PHP_INT_MAX if not provided
                            return $query->havingRaw('sell_wage BETWEEN ? AND ?', [$wage_from, $wage_to]);
                        })
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
                                    ELSE 0
                                END as final_price
                            "),
                            DB::raw("
                            CASE
                                WHEN count > 0 THEN
                                    FLOOR(
                                        (
                                            (
                                                $marketPrice->price * (COALESCE(percentage_sell_wage, 0) / 100) +
                                                (COALESCE(tomans_sell_wage, 0))
                                            ) /
                                            (
                                                $marketPrice->price +
                                                (
                                                    $marketPrice->price *
                                                    (COALESCE(percentage_sell_wage, 0) / 100) +
                                                    (COALESCE(tomans_sell_wage, 0))
                                                )
                                            )
                                        ) * 100
                                    )
                                ELSE 0
                            END as sell_wage"
                            )
                        ]);

                    // Apply sorting based on the sort parameter
                    if ($sort == 2) {
                        $query->orderBy('final_price', 'asc'); // Cheapest
                    } elseif ($sort == 3) {
                        $query->orderBy('final_price', 'desc'); // Most expensive
                    }elseif ($sort == 4) {
                        $query->orderBy('sell_wage', 'asc'); // Low wage
                    }
                },
                'imageVariety' => function($query) use ($vat, $marketPrice) {
                    $query->with(['images'])
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
                        ])->orderBy('final_price', 'asc');
                }
            ])
            ->paginate($count??16);

        return response()->json([
            'products' => $products,
            'sort_options' => SortOptions::orderBy('sort')->get(['id','title']),
            'categories' => Category::all(),
            'colors' => Color::all(),
        ]);
    }
}
