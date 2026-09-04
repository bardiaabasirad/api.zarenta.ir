<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRemovePropertyRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Option;
use App\Models\Category;
use App\Models\Color;
use App\Models\Directory;
use App\Models\MarketPrice;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Property;
use App\Models\Setting;
use App\Models\SizeUnit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index()
    {
        $marketPrice = MarketPrice::latest()->first();
        $vat = Setting::where('option_key','value_added_tax')->firstOrFail()->option_value;

        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count')??config('app.per_page');

        $products = Product::query();

        $products = $products->select('id','title','created_at')
            ->withCount(['varieties as total_count' => function($query){
                $query->select(DB::raw('SUM(count)'));
            }])
            ->when(request()->has('code'), function($query){
                $query->where('id', 'like', '%' . request('code') . '%');
            })
            ->when(request()->has('title'), function($query){
                $query->where('title', 'like', '%' . request('title') . '%');
            })
            ->when(request()->has('start_date') && request()->has('end_date'), function ($query){
                $startDate = Carbon::parse(request('start_date'))->startOfDay();
                $endDate = Carbon::parse(request('end_date'))->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->when(request()->has('category_id'), function ($query) {
                $query->whereHas('categories', function ($subQuery) {
                    $subQuery->where('categories.id', request('category_id'));
                });
            })
            ->when(request()->has('directory_id'), function ($query) {
                $query->whereHas('directories', function ($subQuery) {
                    $subQuery->where('directories.id', request('directory_id'));
                });
            })
            ->when(request()->has('property_id'), function ($query) {
                $query->whereHas('properties', function ($subQuery) {
                    $subQuery->where('properties.id', request('property_id'));
                });
            })
            ->when(request()->has('price_from') && request()->has('price_to'), function ($query) use ($vat, $marketPrice) {
                $min = request('price_from');
                $max = request('price_to');

                // Filter products to only include those with at least one variety where final_price is between min and max
                $query->whereHas('variety', function ($query) use ($min, $max, $vat, $marketPrice) {
                    $query->where('count', '>', 0)
                        ->join('products', 'varieties.product_id', '=', 'products.id')
                        ->whereRaw("
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
                            END BETWEEN ? AND ?",
                            [$min, $max]
                        );
                });
            })
            ->with([
                'variety' => function($query) use ($vat, $marketPrice) {
                    $query->with(['color','images'])
                        ->where('count', '>', 0)
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
                // اگر تعداد تنوع صفر باشد آنگاه تنوعی برگشت نخواهد خورد و بنابراین تصویری هم برای نمایش دادن نداریم
                // باید توسط یک رابطه دیگر (imageVariety) بدون شرط تعداد یک تنوع که کمترین قیمت را دارد برگردانیم و تصویر این تنوع را نمایش دهیم
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
                        ])
                        ->orderBy('final_price', 'asc');
                }
            ])
            ->orderBy($sortBy??'created_at', $dir??'asc');

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

    public function list()
    {
        return response()->json(Product::active()->get(['id','title']));
    }

    public function show(Product $product)
    {
        $product->load(['images','size_unit','varieties' => function($query){
            $query->select('id','weight','count','size','gold_price','product_id','created_at');
        }, 'properties' => function($query){
            $query->select('id','title');
        }, 'categories' => function($query){
            $query->select('id','title');
        }, 'directories' => function($query){
            $query->select('id','title');
        }]);

        $increaseReasons = Option::where('key', 'increase_inventory_count')->get();
        $decreaseReasons = Option::where('key', 'decrease_inventory_count')->get();
        return response()->json([
            'product' => new ProductResource($product),
            'increase_reasons' => $increaseReasons,
            'decrease_reasons' => $decreaseReasons,
            'colors' => Color::all(),
            'categories' => Category::all(),
            'directories' => Directory::all(),
            'size_units' => SizeUnit::all(),
            'properties' => Property::all(),
            'gold_price' => MarketPrice::latest()->select('id','price','created_at')->first(),
        ]);
    }

    public function create()
    {
        return response()->json([
            'size_units' => SizeUnit::get(),
            'categories' => Category::get(),
            'directories' => Directory::all(),
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated() + ['created_by' => Auth::id()]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                // Generate a unique file name
                $filename = uniqid() . '.' . $image->getClientOriginalExtension();

                // Move the file to the public products directory
                $path = $image->storeAs('products', $filename);

                // Create a new record for the image in the database
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $path
                ]);
            }
        }

        if ($request->directories){
            $maxOrders = DB::table('directory_product')
                ->whereIn('directory_id', $request->directories)
                ->select('directory_id',DB::raw('MAX(`order`) AS max_order'))
                ->groupBy('directory_id')
                ->get()
                ->pluck('max_order', 'directory_id');

            foreach ($request->directories as $directory_id){
                $maxOrder = $maxOrders->get($directory_id) ?? 0;
                DB::table('directory_product')->insert([
                    'product_id' => $product->id,
                    'directory_id' => $directory_id,
                    'order' => $maxOrder+1,
                ]);
            }

            if ($request->categories){
                $product->categories()->attach($request->categories);
            }
        }

        return response()->json([
            'product' => $product,
            'message' => trans('messages.a_new_product_has_been_created')
        ], 201);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                // Generate a unique file name
                $filename = uniqid() . '.' . $image->getClientOriginalExtension();

                // Move the file to the public products directory
                $path = $image->storeAs('products', $filename);

                // Create a new record for the image in the database
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $path
                ]);
            }
        }

        if ($request->directories){
            $maxOrders = DB::table('directory_product')
                ->whereIn('directory_id', $request->directories)
                ->select('directory_id',DB::raw('MAX(`order`) AS max_order'))
                ->groupBy('directory_id')
                ->get()
                ->pluck('max_order', 'directory_id');

            // Get existing directory IDs for the product
            $existingDirectoryIds = $product->directories()->pluck('id');

            // Handle additions
            $directoriesToAdd = array_diff($request->directories, $existingDirectoryIds->toArray());
            foreach ($directoriesToAdd as $directoryId) {
                $maxOrder = $maxOrders->get($directoryId) ?? 0;
                DB::table('directory_product')->insert([
                    'product_id' => $product->id,
                    'directory_id' => $directoryId,
                    'order' => $maxOrder + 1,
                ]);
            }

            // Handle removals
            $directoriesToRemove = array_diff($existingDirectoryIds->toArray(), $request->directories);
            $product->directories()->detach($directoriesToRemove);
        }

        if ($request->categories){
            $product->categories()->sync($request->categories);
        }

        if ($request->removed_images){
            $images = ProductImage::whereIn('id', $request->removed_images)->get();
            foreach ($images as $image) {
                $image->delete();
            }
        }

        $product->load([
            'images',
            'varieties' => function($query){
                $query->select('id','weight','count','size','gold_price','product_id','created_at');
            },
            'properties' => function($query){
                $query->select('id','title');
            }
        ]);

        return response()->json([
            'product' => new ProductResource($product),
            'message' => trans('messages.the_product_has_been_successfully_updated')
        ]);
    }

    public function updateProductProperties(StorePropertyRequest $request){

        $product = Product::findOrFail($request->product_id);

        if ($request->title) {
            // Create a new property
            $property = new Property();
            $property->title = $request->title;
            $property->values = $request->values;
            $property->save();
        } else {
            // Retrieve an existing property
            $property = Property::findOrFail($request->property_id);

            // Merge and remove duplicates
            $property->values = array_unique(array_merge($property->values, $request->values), SORT_REGULAR);

            $property->save();
        }

        // Sync the property with the product
        $product->properties()->detach([$property->id]);
        $product->properties()->attach([$property->id => ['values' => $request->values]]);

        $product->load(['varieties' => function($query){
            $query->select('id','weight','count','size','gold_price','product_id','created_at');
        }, 'properties' => function($query){
            $query->select('id','title');
        }]);

        return response()->json([
            'product' => new ProductResource($product),
            'message' => trans('messages.the_property_has_been_added_successfully_to_the_product')
        ]);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'message' => trans('messages.the_product_has_been_successfully_removed')
        ]);
    }

    public function removeProductProperties(ProductRemovePropertyRequest $request, Product $product)
    {
        $product->properties()->detach($request->property_id);

        return response()->json([
            'message' => trans('messages.the_product_property_has_been_successfully_removed')
        ]);
    }
}
