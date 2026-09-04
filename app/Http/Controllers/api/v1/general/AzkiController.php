<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use App\Http\Resources\AzkiProductResource;
use App\Models\MarketPrice;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Shetabit\Multipay\Invoice;
use Shetabit\Payment\Facade\Payment;

class AzkiController extends Controller
{
    public function payment()
    {
        return Payment::via('azki')->purchase(
            (new Invoice)->detail([
                'mobile' => '09123753475',
                'items' => [
                    [
                        "name" => "پلاک طلای آبشده زربد",
                        "count" => 1,
                        "image_url" => 'https://api.zhikgold.ir/api/v1/images/public/products/6649e66e62f49.jpg?w=700&h=700',
                        "amount" => 3151000,
                        "url" => "https://zhikgold.ir/products/55"
                    ]
                ]
            ])->amount(10000),
            function($driver, $purchase_id) {
                echo "$purchase_id";
            }
        )->pay()->toJson();
    }

    public function products()
    {
        $marketPrice = MarketPrice::latest()->first();

        $products = Product::active()->latest()
            ->whereHas('variety', function($query){
                $query->where('count', '>', 0);
            })
            ->with([
                'variety' => function($query) use ($marketPrice) {
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
            },
                'categories'
            ])
            ->get();

        return response()->json(AzkiProductResource::collection($products));
    }
}
