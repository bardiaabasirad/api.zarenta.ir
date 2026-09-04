<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DirectoryStoreRequest;
use App\Http\Requests\DirectoryUpdateRequest;
use App\Models\Directory;
use App\Models\MarketPrice;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DirectoryController extends Controller
{
    public function products(Directory $directory)
    {
        $marketPrice = MarketPrice::latest()->first();

        $count = request()->input('count')??config('app.per_page');

        $products = $directory->products()
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
                'variety' => function($query) use ($marketPrice) {
                    $query
                        ->where('count', '>', 0)
                        ->with(['color','images'])
                        ->addSelect([
                            'varieties.*',
                            'count',
                            // Add your final_price calculation here as a select statement
                            DB::raw("CASE
                                WHEN count > 0 THEN
                                        ROUND(
                                            (SELECT @initial_value := {$marketPrice->price} * `weight`) +
                                            @initial_value * (IFNULL(`percentage_sell_wage`, 0) / 100) +
                                            (IFNULL(`tomans_sell_wage`, 0)) +
                                            @initial_value * (IFNULL(`percentage_profit`, 0) / 100) +
                                            (IFNULL(`tomans_profit`, 0)) -
                                            @initial_value * (IFNULL(`percentage_discount`, 0) / 100) -
                                            (IFNULL(`tomans_discount`, 0))
                                        )
                                ELSE NULL
                            END as final_price"
                            ),
                        ])
                        ->orderBy('final_price', 'asc');
                },
                'imageVariety' => function($query) use ($marketPrice) {
                    $query->with(['images'])
                        ->addSelect([
                            'varieties.*',
                            'count',
                            // Add your final_price calculation here as a select statement
                            DB::raw("ROUND(
                                (SELECT @initial_value := {$marketPrice->price} * `weight`) +
                                @initial_value * (IFNULL(`percentage_sell_wage`, 0) / 100) +
                                (IFNULL(`tomans_sell_wage`, 0)) +
                                @initial_value * (IFNULL(`percentage_profit`, 0) / 100) +
                                (IFNULL(`tomans_profit`, 0)) -
                                @initial_value * (IFNULL(`percentage_discount`, 0) / 100) -
                                (IFNULL(`tomans_discount`, 0))
                            ) as final_price"
                            ),
                        ])->orderBy('final_price', 'asc');
                }
            ])
            ->orderByPivot('order', 'DESC');

            if (request()->has('pagination') && request()->input('pagination') == 'no'){
                $products = $products->get();
            }
            else{
                $products = $products->paginate($count);
            }

        return response()->json([
            'products' => $products,
            'market_price' => $marketPrice,
        ]);
    }

    public function changeOrder(Request $request)
    {
        $pivot_1 = json_decode($request->pivot_1);
        DB::table('directory_product')->where('directory_id', $pivot_1->directory_id)->where('product_id', $pivot_1->product_id)->update([
           'order' => $pivot_1->order
        ]);

        $pivot_2 = json_decode($request->pivot_2);
        DB::table('directory_product')->where('directory_id', $pivot_2->directory_id)->where('product_id', $pivot_2->product_id)->update([
           'order' => $pivot_2->order
        ]);

        return response()->json([
            'message' => trans('messages.order_of_sections_updated_successfully'),
        ]);
    }

    public function index()
    {
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count')??config('app.per_page');
        $code = request()->input('code');
        $title = request()->input('title');
        $min = request()->input('min');
        $max = request()->input('max');
        $start_date = request()->input('start_date');
        $end_date = request()->input('end_date');

        $directories = Directory::query();

        $directories = $directories->select('id','title','created_at')
            ->when(isset($code), function ($query) use ($code){
                $query->where('id', 'like', '%' .$code . '%');
            })
            ->when(isset($title), function ($query) use ($title){
                $query->where('title', 'like', '%' . $title . '%');
            })
            ->when(isset($start_date) && isset($end_date), function ($query) use ($start_date, $end_date){
                $startDate = Carbon::parse($start_date)->startOfDay();
                $endDate = Carbon::parse($end_date)->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->when(isset($min), function ($query) use ($min) {
                $query->having('products_count', '>=', $min);
            })
            ->when(isset($max), function ($query) use ($max) {
                $query->having('products_count', '<=', $max);
            })
            ->withCount(['products as products_count'])
            ->orderBy($sortBy??'created_at', $dir??'asc')->paginate($count);

        $directoryWithMostProducts = Directory::withCount('products')
            ->orderBy('products_count', 'desc')
            ->first();

        return response()->json([
            'directories' => $directories,
            'directory_with_most_products' => $directoryWithMostProducts->products_count
        ]);
    }

    public function store(DirectoryStoreRequest $request)
    {
        Directory::create($request->validated());

        return response()->json([
            'message' => trans('messages.a_new_directory_has_been_created')
        ], 201);
    }

    public function update(DirectoryUpdateRequest $request, Directory $directory)
    {
        $directory->update($request->validated());

        return response()->json([
            'directory' => $directory->loadCount(['products as products_count']),
            'message' => trans('messages.the_directory_has_been_successfully_updated')
        ]);
    }

    public function show(Directory $directory)
    {
        $directory->loadCount(['products as products_count']);
        return response()->json([
            'directory' => $directory
        ]);
    }

    public function destroy(Directory $directory)
    {
        $directory->delete();

        return response()->json([
            'message' => trans('messages.the_directory_has_been_successfully_removed')
        ]);
    }
}
