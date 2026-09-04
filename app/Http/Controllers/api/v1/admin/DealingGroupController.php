<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Events\DealingGroupUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\DealingGroupStoreRequest;
use App\Http\Requests\DealingGroupUpdateRequest;
use App\Models\Configs\DealingGroupMetalItemConfig;
use App\Models\DealingGroup;
use App\Models\MetalItem;
use DB;
use Illuminate\Http\Request;

class DealingGroupController extends Controller
{
    public function index()
    {
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');
        $id = request()->input('id');
        $name = request()->input('name');

        $dealerGroups = DealingGroup::query();

        $dealerGroups = $dealerGroups->select('id','name')
            ->withCount('metalTraders')
            ->when(isset($id), function ($query) use ($id){
                $query->where('id', 'like', '%' .$id . '%');
            })
            ->when(isset($name), function ($query) use ($name){
                $query->where('name', 'like', '%' . $name . '%');
            })
            ->orderBy($sortBy??'created_at', $dir??'desc')
            ->paginate($count??config('app.per_page'));

        return response()->json($dealerGroups);
    }

    public function create()
    {
        return response()->json([
            'meta' => [
                'tolerance_types' => [
                    [
                        'value' => 'fixed_amount',
                        'name' => 'مبلغ ثابت',
                    ],
                    [
                        'value' => 'percentage',
                        'name' => 'درصد',
                    ]
                ],
                'display_modes' => [
                    [
                        'value' => 'quotation',
                        'name' => 'مظنه',
                    ],
                    [
                        'value' => 'per_gram',
                        'name' => 'گرم',
                    ]
                ],
            ],
            'items' => MetalItem::all(),
        ]);
    }

    public function show(DealingGroup $group)
    {
        return response()->json([
            'group' => $group->load(['metalItems' => function ($query) {
                $query->orderBy('sort_order', 'asc')->select('id', 'title', 'unit');
            }])->loadCount('metalTraders'),
            'metal_items' => MetalItem::withoutGlobalScope('visible')->orderBy('sort_order', 'asc')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string',

            'configurations'                    => 'required|array',
            'configurations.*.metal_item_id'    => 'required|exists:metal_items,id',
            'configurations.*.tolerance_type'   => 'required|string',
            'configurations.*.display_mode'     => 'required|string',
            'configurations.*.min_order'        => 'nullable|numeric',
            'configurations.*.max_order'        => 'nullable|numeric',
            'configurations.*.buy_fee_margin'   => 'nullable|numeric',
            'configurations.*.sell_fee_margin'  => 'nullable|numeric',
        ]);

        DB::transaction(function () use ($validated) {

            $group = DealingGroup::create([
                'name'         => $validated['name'],
            ]);

            foreach ($validated['configurations'] as $config) {
                $group->metalItems()->attach($config['metal_item_id'], [
                    'tolerance_type'    => $config['tolerance_type'],
                    'display_mode'      => $config['display_mode'],
                    'min_order'         => $config['min_order'] ?? null,
                    'max_order'         => $config['max_order'] ?? null,
                    'buy_fee_margin'    => $config['buy_fee_margin'] ?? null,
                    'sell_fee_margin'   => $config['sell_fee_margin'] ?? null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        });

        return response()->json([
            'message' => 'گروه جدید با موفقیت ایجاد شد'
        ], 201);
    }

    public function update(DealingGroupUpdateRequest $request, DealingGroup $group)
    {
        $group->update($request->validated());

        return response()->json([
            'group' => $group,
            'message' => 'گروه با موفقیت بروزرسانی شد',
        ]);
    }




    public function updateDealingGroupMetalItem(DealingGroup $group, MetalItem $metalItem, Request $request)
    {
        $group->metalItems()->updateExistingPivot(
            $metalItem->id,
            $request->only([
                'tolerance_type',
                'display_mode',
                'min_order',
                'max_order',
                'buy_fee_margin',
                'sell_fee_margin'
            ])
        );

        DealingGroupUpdated::dispatch($group);

        return response()->json([
            'message' => 'گروه با موفقیت بروزرسانی شد',
        ]);
    }

    public function storeDealingGroupMetalItem(Request $request, DealingGroup $group)
    {
        $request->validate([
            'metal_item_id'     => 'required|exists:metal_items,id',
            'tolerance_type'    => 'required|string',
            'display_mode'      => 'required|string',
            'min_order'         => 'nullable',
            'max_order'         => 'nullable',
            'buy_fee_margin'    => 'nullable',
            'sell_fee_margin'   => 'nullable',
        ]);

        $group->metalItems()->attach($request->metal_item_id, [
            'tolerance_type' => $request->tolerance_type,
            'display_mode' => $request->display_mode,
            'min_order' => $request->min_order,
            'max_order' => $request->max_order,
            'buy_fee_margin' => $request->buy_fee_margin,
            'sell_fee_margin' => $request->sell_fee_margin,
        ]);

        DealingGroupUpdated::dispatch($group);

        return response()->json([
            'message' => 'تنظیمات با موفقیت افزوده شد',
            'group' => $group->load('metalItems')->loadCount('metalTraders'),
        ], 201);
    }

    public function deleteDealingGroupMetalItem(DealingGroup $group, $metalItemId)
    {
        $group->metalItems()->detach($metalItemId);

        DealingGroupUpdated::dispatch($group);

        return response()->json([
            'message' => 'نگاشت با موفقیت حذف شد',
            'group' => $group->load('metalItems')->loadCount('metalTraders'),
        ]);
    }

}
