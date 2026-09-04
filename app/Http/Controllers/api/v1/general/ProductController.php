<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductShowGeneralReasource;
use App\Models\MarketPrice;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()->active();

        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');

        $products = $products->with(['image','variety' => function($query){
                $query->with(['color'])->orderBy('weight');
            }])
            ->orderBy($sortBy??'created_at', $dir??'asc')
            ->paginate($count??10);

        return response()->json($products);
    }

    public function show($product)
    {
        $marketPrice = MarketPrice::latest()->first();
        $vat = Setting::where('option_key','value_added_tax')->firstOrFail()->option_value;

        $product = Product::active()->with([
            'size_unit',
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
            },
            'varieties' => function($query){
                $query->where('count', '>', 0)
                    ->with(['color','images' => function($query){
                        $query->orderBy('orders')->withPivot('orders');
                    }]);
            },
            'properties' => function($query){
                $query->select('id','title');
            }
        ])
        ->where('id', $product)->firstOrFail();

        return response()->json(new ProductShowGeneralReasource($product));
    }
}
