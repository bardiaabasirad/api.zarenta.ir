<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Events\SettingsChanged;
use App\Http\Controllers\Controller;
use App\Models\MetalItem;
use App\Models\MetalItemGroup;
use DB;
use Illuminate\Http\Request;

class MetalItemController extends Controller
{
    public function index()
    {
        $count  = request()->input('count');
        $id     = request()->input('id');
        $title  = request()->input('title');

        $metal_items = MetalItem::query()
            ->withoutGlobalScope('visible')
            ->join('metal_item_groups', 'metal_items.metal_item_group_id', '=', 'metal_item_groups.id')
            ->select('metal_items.id', 'metal_items.title', 'metal_items.metal_item_group_id')
            ->when(isset($id), function ($query) use ($id) {
                $query->where('metal_items.id', 'like', '%' . $id . '%');
            })
            ->when(isset($title), function ($query) use ($title) {
                $query->where('metal_items.title', 'like', '%' . $title . '%');
            })
            ->with(['group' => function ($query) {
                $query->select('id', 'title');
            }])
            ->orderBy('metal_item_groups.sort_order')
            ->orderBy('metal_items.sort_order')
            ->paginate($count ?? config('app.per_page'));

        return response()->json([
            'metal_items' => $metal_items
        ]);
    }

    public function create()
    {
        return response()->json([
            'meta' => [
                'metal_item_units' => [
                    [
                        'value' => 'gram',
                        'name' => 'گرم',
                    ],
                    [
                        'value' => 'count',
                        'name' => 'تعداد',
                    ]
                ]
            ],
            'metal_item_groups' => MetalItemGroup::all(),
        ]);
    }

    public function show(MetalItem $metalItem)
    {
        return response()->json([
            'meta' => [
                'metal_item_units' => [
                    [
                        'value' => 'gram',
                        'name' => 'گرم',
                    ],
                    [
                        'value' => 'count',
                        'name' => 'تعداد',
                    ]
                ]
            ],
            'metal_item_groups' => MetalItemGroup::all(),
            'metal_item' => $metalItem,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|min:3|max:255|unique:metal_items,title',
            'metal_item_group_id' => 'required|exists:metal_item_groups,id',
            'is_buy_active' => 'required',
            'is_sell_active' => 'required',
            'is_visible' => 'required',
            'kimia_product_id' => 'sometimes|required',
            'requires_accounting_document_id' => 'required',
            'accounting_document_id' => 'nullable',
            'purity' => 'required',
            'equivalent_to' => 'required|numeric|min:0|max:9999999.999|decimal:0,3',
            'unit' => 'required',
        ], [
            'title.required' => 'عنوان الزامی است',
            'title.min' => 'عنوان باید حداقل ۳ کاراکتر باشد',
            'title.unique' => 'این عنوان قبلا استفاده شده است'
        ]);

        // محاسبه آخرین sort_order در گروه مربوطه
        $maxSortOrder = MetalItem::where('metal_item_group_id', $validated['metal_item_group_id'])
            ->max('sort_order') ?? 0;

        // اضافه کردن sort_order به داده‌های validated
        $validated['sort_order'] = $maxSortOrder + 1;

        // ذخیره در دیتابیس
        MetalItem::create($validated);

        return response()->json(['message' => 'فلز جدید با موفقیت ثبت شد']);
    }

    public function update(Request $request, MetalItem $metalItem)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|min:3|max:255|unique:metal_items,title,' . $metalItem->id,
            'metal_item_group_id' => 'sometimes|required|exists:metal_item_groups,id',
            'buy_sell_spread' => 'sometimes|required|integer',
            'is_buy_active' => 'sometimes|required',
            'is_sell_active' => 'sometimes|required',
            'is_visible' => 'sometimes|required',
            'kimia_product_id' => 'sometimes|required',
            'requires_accounting_document_id' => 'sometimes|required',
            'accounting_document_id' => 'nullable',
            'purity' => 'sometimes|required',
            'equivalent_to' => 'sometimes|required|numeric|min:0|max:9999999.999|decimal:0,3',
            'unit' => 'sometimes|required|in:gram,count',
        ]);

        $shouldFireEvent = array_key_exists('is_buy_active', $validated)
            || array_key_exists('is_sell_active', $validated)
            || array_key_exists('buy_sell_spread', $validated)
            || array_key_exists('is_visible', $validated);

        $metalItem->update($validated);

        if ($shouldFireEvent) {
            event(new SettingsChanged());
        }

        return response()->json([
            'message' => 'فلز با موفقیت بروزرسانی شد'
        ]);
    }

    public function getMappings($priceSource)
    {
        $mappings = DB::table('metal_item_price_source')
            ->where('price_source_id', $priceSource)
            ->select('metal_item_id', 'rate_external_identifier')
            ->get()
            ->groupBy('rate_external_identifier')
            ->map(fn($items) => $items->pluck('metal_item_id')->toArray())
            ->toArray();

        return response()->json($mappings);
    }

    public function updateSortOrder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:metal_items,id',
            'items.*.sort_order' => 'required|integer|min:1'
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->items as $item) {
                MetalItem::where('id', $item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json([
            'message' => 'ترتیب با موفقیت به‌روزرسانی شد',
            'success' => true
        ]);
    }
}
