<?php

namespace App\Http\Controllers\api\v1\external;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductShowGeneralReasource;
use App\Models\MarketPrice;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function home()
    {
        $marketPrice = MarketPrice::latest()->first();

        $products = Product::active()->latest()
            ->whereHas('variety', function($query){
                $query->where('count', '>', 0);
            })
            ->with(['variety' => function($query) use ($marketPrice) {
                $query
                    ->where('count', '>', 0)
                    ->with(['color','images'])
                    ->addSelect([
                        'varieties.*',
                        'count',
                        // Add your final_price calculation here as a select statement
                        DB::raw("
                            CASE
                                WHEN count > 0 THEN
                                    CEIL(
                                        (SELECT @initial_value := {$marketPrice->price} * `weight`) +
                                        @initial_value * (COALESCE(`percentage_sell_wage`, 0) / 100) +
                                        (COALESCE(`tomans_sell_wage`, 0)) +
                                        @initial_value * (COALESCE(`percentage_profit`, 0) / 100) +
                                        (COALESCE(`tomans_profit`, 0)) -
                                        @initial_value * (COALESCE(`percentage_discount`, 0) / 100) -
                                        (COALESCE(`tomans_discount`, 0))
                                    )
                                ELSE NULL
                            END as final_price"
                        ),
                    ])
                    ->orderBy('final_price', 'asc');
            }])
            ->limit(16)->get();

        $Bestsellers = Product::active()->latest()
            ->whereHas('variety', function($query){
                $query->where('count', '>', 0);
            })
            ->with(['variety' => function($query) use ($marketPrice) {
                $query
                    ->where('count', '>', 0)
                    ->with(['color','images'])
                    ->addSelect([
                        'varieties.*',
                        'count',
                        // Add your final_price calculation here as a select statement
                        DB::raw("
                            CASE
                                WHEN count > 0 THEN
                                    CEIL(
                                        (SELECT @initial_value := {$marketPrice->price} * `weight`) +
                                        @initial_value * (COALESCE(`percentage_sell_wage`, 0) / 100) +
                                        (COALESCE(`tomans_sell_wage`, 0)) +
                                        @initial_value * (COALESCE(`percentage_profit`, 0) / 100) +
                                        (COALESCE(`tomans_profit`, 0)) -
                                        @initial_value * (COALESCE(`percentage_discount`, 0) / 100) -
                                        (COALESCE(`tomans_discount`, 0))
                                    )
                                ELSE NULL
                            END as final_price"
                        ),
                    ])
                    ->orderBy('final_price', 'asc');
            }])
            ->whereIn('id', [55,56])->get();

        return response()->json([
            'products' => $products,
            'bestsellers' => $Bestsellers,
        ]);
    }

    public function show($product)
    {
        $marketPrice = MarketPrice::latest()->first();

        $product = Product::active()->with([
            'size_unit',
            'imageVariety' => function($query) use ($marketPrice) {
                $query
                    ->with(['images'])
                    ->addSelect([
                        'varieties.*',
                        'count',
                        // Add your final_price calculation here as a select statement
                        DB::raw("
                            ROUND(
                                (SELECT @initial_value := {$marketPrice->price} * `weight`) +
                                @initial_value * (IFNULL(`percentage_sell_wage`, 0) / 100) +
                                (IFNULL(`tomans_sell_wage`, 0)) +
                                @initial_value * (IFNULL(`percentage_profit`, 0) / 100) +
                                (IFNULL(`tomans_profit`, 0)) -
                                @initial_value * (IFNULL(`percentage_discount`, 0) / 100) -
                                (IFNULL(`tomans_discount`, 0))
                            ) as final_price"
                        ),
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
