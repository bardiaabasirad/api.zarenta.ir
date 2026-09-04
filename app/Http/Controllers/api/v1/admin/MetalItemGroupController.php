<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\MetalItemGroup;
use DB;
use Illuminate\Http\Request;

class MetalItemGroupController extends Controller
{
    public function index()
    {
        $count = request()->input('count');
        $id = request()->input('id');
        $title = request()->input('title');

        $metal_item_groups = MetalItemGroup::query();

        $metal_item_groups = $metal_item_groups->select('id', 'title', 'sort_order')
            ->when(isset($id), function ($query) use ($id){
                $query->where('id', 'like', '%' .$id . '%');
            })
            ->when(isset($title), function ($query) use ($title){
                $query->where('title', 'like', '%' . $title . '%');
            })
            ->withCount('metalItems')
            ->orderBy('sort_order', 'asc')
            ->paginate($count??config('app.per_page'));

        return response()->json([
            'metal_item_groups' => $metal_item_groups
        ]);
    }

    public function show(MetalItemGroup $metalItemGroup)
    {
        // if required to show all metal items so using withoutGlobalScope('visible') on metalItems relation

        return response()->json([
            'metal_item_group' => $metalItemGroup->load(['metalItems' => function ($query) {
                $query->orderBy('sort_order', 'asc');
            }])
        ]);
    }

    public function updateOrder(Request $request)
    {
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'exists:metal_item_groups,id'],
            'items.*.order' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($request) {

            foreach ($request->items as $item) {

                MetalItemGroup::query()
                    ->where('id', $item['id'])
                    ->update([
                        'sort_order' => $item['order']
                    ]);
            }
        });

        return response()->json([
            'message' => 'ترتیب با موفقیت بروزرسانی شد'
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|min:3|max:255|unique:metal_item_groups,title',
        ], [
            'title.required' => 'عنوان الزامی است',
            'title.min' => 'عنوان باید حداقل ۳ کاراکتر باشد',
            'title.unique' => 'این عنوان قبلا استفاده شده است'
        ]);

        // ذخیره در دیتابیس
        MetalItemGroup::create($validated);

        return response()->json(['message' => 'گروه با موفقیت ثبت شد']);
    }

    public function update(Request $request, MetalItemGroup $metalItemGroup)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|min:3|max:255|unique:metal_item_groups,title,' . $metalItemGroup->id,
        ]);

        $metalItemGroup->update($validated);

        return response()->json([
            'message' => 'گروه با موفقیت بروزرسانی شد'
        ]);
    }
}
