<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\MetalItem;
use App\Models\PriceSource;
use Illuminate\Http\Request;

class PriceSourceController extends Controller
{
    public function index()
    {
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');
        $id = request()->input('id');

        $price_sources = PriceSource::query();

        $price_sources = $price_sources->select('id', 'name')
            ->when(isset($id), function ($query) use ($id){
                $query->where('id', 'like', '%' .$id . '%');
            })
            ->withCount('metalItems')
            ->orderBy($sortBy??'created_at', $dir??'desc')
            ->paginate($count??config('app.per_page'));

        return response()->json([
            'price_sources' => $price_sources
        ]);
    }

    public function show(PriceSource $priceSource)
    {
        return response()->json([
            'metal_items' => MetalItem::orderBy('sort_order', 'asc')->get(),
            'price_source' => $priceSource->load(['metalItems' => function ($query) {
                $query->orderBy('sort_order', 'asc');
            }]),
        ]);
    }

    public function create()
    {
        return response()->json([
            'metal_items' => MetalItem::all(),
        ]);
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|min:3|max:255|unique:price_sources,name',
            'metal_items' => 'array',
            'metal_items.*.metal_item_id' => 'required|exists:metal_items,id',
            'metal_items.*.rate_external_identifier' => 'required|string',
        ], [
            'name.required' => 'عنوان کانال الزامی است',
            'name.min' => 'عنوان کانال باید حداقل ۳ کاراکتر باشد',
            'name.unique' => 'این عنوان کانال قبلا استفاده شده است',
        ]);

        $priceSource = PriceSource::create([
            'name' => $validated['name'],
        ]);

        if (!empty($validated['metal_items'])) {
            $syncData = [];
            foreach ($validated['metal_items'] as $item) {
                $syncData[$item['metal_item_id']] = ['rate_external_identifier' => $item['rate_external_identifier']];
            }
            $priceSource->metalItems()->sync($syncData);
        }

        return response()->json(['message' => 'مرجع نرخ با موفقیت ایجاد شد']);
    }

    public function update(Request $request, PriceSource $priceSource)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|min:3|max:255|unique:price_sources,name,' . $priceSource->id,
        ]);

        $priceSource->update($validated);

        return response()->json([
            'message' => 'مرجع نرخ با موفقیت بروزرسانی شد'
        ]);
    }

    public function storePriceSourceMetalItem(Request $request, PriceSource $priceSource)
    {
        $request->validate([
            'metal_item_id' => 'required|exists:metal_items,id',
            'rate_external_identifier' => 'required|string|max:255',
        ]);

        $priceSource->metalItems()->attach($request->metal_item_id, [
            'rate_external_identifier' => $request->rate_external_identifier,
        ]);

        return response()->json([
            'message' => 'نگاشت با موفقیت افزوده شد',
            'price_source' => $priceSource->load(['metalItems' => function ($query) {
                $query->orderBy('sort_order', 'asc');
            }]),
        ], 201);
    }

    public function updatePriceSourceMetalItem(Request $request, PriceSource $priceSource, $metalItemId)
    {
        $validated = $request->validate([
            'rate_external_identifier' => 'sometimes|nullable|string|max:255',
            'order_external_identifier' => 'sometimes|nullable|string|max:255',
        ]);

        if (empty($validated)) {
            return response()->json([
                'message' => 'هیچ داده‌ای برای به‌روزرسانی ارسال نشده است',
            ], 422);
        }

        $priceSource->metalItems()->updateExistingPivot($metalItemId, $validated);

        return response()->json([
            'message' => 'نگاشت با موفقیت به‌روزرسانی شد',
            'price_source' => $priceSource->load(['metalItems' => function ($query) {
                $query->orderBy('sort_order', 'asc');
            }]),
        ]);
    }

    public function deletePriceSourceMetalItem(PriceSource $priceSource, $metalItemId)
    {
        $priceSource->metalItems()->detach($metalItemId);

        return response()->json([
            'message' => 'نگاشت با موفقیت حذف شد',
            'price_source' => $priceSource->load(['metalItems' => function ($query) {
                $query->orderBy('sort_order', 'asc');
            }]),
        ]);
    }

}
