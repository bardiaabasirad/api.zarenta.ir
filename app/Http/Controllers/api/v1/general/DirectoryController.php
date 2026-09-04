<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use App\Models\Directory;
use App\Models\MarketPrice;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class DirectoryController extends Controller
{
    public function products()
    {
        $vat = Setting::where('option_key','value_added_tax')->firstOrFail()->option_value;
        $slug = request()->input('slug');
        $directory = Directory::where('slug', $slug)->firstOrFail();
        $marketPrice = MarketPrice::latest()->first();
        $count = request()->input('count');

        $products = $directory->products()
            ->active()
            ->withPivot('order') // Eager load the 'order' column from the pivot table
            ->select('id','title','created_at')
            ->withCount(['varieties as total_count' => function($query){
                $query->select(DB::raw('SUM(count)'));
            }])
            ->when(request()->has('category_id'), function ($query) {
                $query->whereHas('categories', function ($subQuery) {
                    $subQuery->where('categories.id', request()->input('category_id'));
                });
            })
            ->when(request()->has('directory_id'), function ($query) {
                $query->whereHas('directories', function ($subQuery) {
                    $subQuery->where('directories.id', request()->input('directory_id'));
                });
            })
            ->when(request()->has('property_id'), function ($query) {
                $query->whereHas('properties', function ($subQuery) {
                    $subQuery->where('properties.id', request()->input('property_id'));
                });
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
            ->orderByPivot('order', 'DESC')
            ->paginate($count??24);

        return response()->json([
            'products' => $products,
            'directory' => $directory,
        ]);
    }
}
