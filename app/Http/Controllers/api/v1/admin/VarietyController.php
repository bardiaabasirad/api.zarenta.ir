<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VarietyStoreRequest;
use App\Http\Requests\VarietyUpdateRequest;
use App\Models\Option;
use App\Models\Color;
use App\Models\MarketPrice;
use App\Models\Variety;

class VarietyController extends Controller
{
    public function show(Variety $variety)
    {
        $variety->load([
            'color','images' => function($query){
                $query->orderBy('orders');
            },
            'product' => function($query){
                $query->with('images','size_unit')->select('id','title','size_unit_id');
            }
        ]);

        return response()->json([
            'variety' => $variety,
            'increase_reasons' => Option::where('key', 'increase_inventory_count')->get(),
            'decrease_reasons' => Option::where('key', 'decrease_inventory_count')->get(),
            'colors' => Color::all(),
            'gold_price' => MarketPrice::latest()->select('id','price','created_at')->first(),
        ]);
    }

    public function store(VarietyStoreRequest $request)
    {

        $barcode = $this->generateUniqueBarcode();

        $variety = Variety::create($request->validated() + ['gold_price' => MarketPrice::latest()->first()->price, 'barcode' => $barcode]);

        if ($request->images){
            // Assuming $request->images is an array of image IDs in the order they were received
            // and $request->order is an array of order indexes corresponding to the images
            $imagesWithOrder = [];
            foreach ($request->images as $index => $imageId) {
                $imagesWithOrder[$imageId] = [
                    'product_image_id' => $imageId,
                    'orders' => $index
                ];
            }

            $variety->images()->attach($imagesWithOrder);
        }

        return response()->json([
            'variety' => $variety->only(["id","weight","count","size","gold_price","product_id","created_at"]),
            'message' => 'تنوع جدید با موفقیت ایجاد شد'
        ], 201);
    }

    private function generateUniqueBarcode(): int
    {
        $barcode = random_int(100000, 999999);
        while (Variety::where('barcode', $barcode)->exists()){
            $this->generateUniqueBarcode();
        }

        return $barcode;
    }

    public function update(VarietyUpdateRequest $request, Variety $variety): \Illuminate\Http\JsonResponse
    {
        $variety->updateWithLogging($request->validated(), $request->cause);

        $imagesWithOrder = [];

        if ($request->images){
            foreach ($request->images as $index => $imageId) {
                $imagesWithOrder[$imageId] = [
                    'product_image_id' => $imageId,
                    'orders' => $index
                ];
            }
        }

        $variety->images()->sync($imagesWithOrder);

        return response()->json([
            'variety' => $variety->load(['color','images','product' => function($query){
                $query->with('images','size_unit')->select('id','title','size_unit_id');
            }]),
            'message' => 'تنوع با موفقیت بروزرسانی شد'
        ]);
    }

    public function updateCount(VarietyUpdateRequest $request, Variety $variety): \Illuminate\Http\JsonResponse
    {
        $variety->updateWithLogging($request->validated(), $request->cause);

        return response()->json([
            'message' => 'تنوع با موفقیت بروزرسانی شد'
        ]);
    }
}
