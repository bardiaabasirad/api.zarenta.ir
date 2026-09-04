<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Events\BoardMetalPriceUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\BoardCoinStoreRequest;
use App\Http\Requests\BoardCoinUpdateRequest;
use App\Models\BoardCoin;
use App\Models\MetalItem;
use App\Services\BoardCoinService;
use DB;
use Illuminate\Http\Request;

class BoardCoinController extends Controller
{
    public function index()
    {
        $metalItems = MetalItem::query()
            ->join('metal_item_groups', 'metal_items.metal_item_group_id', '=', 'metal_item_groups.id')
            ->orderBy('metal_item_groups.sort_order')
            ->orderBy('metal_items.sort_order')
            ->select('metal_items.id', 'metal_items.title')
            ->get();

        return response()->json([
            'board_coins' => BoardCoin::with('metalItem')->orderBy('sort_order')->get(),
            'metal_items' => $metalItems,
        ]);
    }

    public function store(BoardCoinStoreRequest $request)
    {
        $data = $request->validated();
        $data['sort_order'] = (BoardCoin::max('sort_order') ?? 0) + 1;

        $boardCoin = BoardCoin::create($data);

        return response()->json([
            'board_coin' => $boardCoin->load('metalItem'),
            'message' => 'سکه با موفقیت به تابلو افزوده شد.',
        ], 201);
    }

    public function update(BoardCoinUpdateRequest $request, BoardCoin $boardCoin)
    {
        $boardCoin->update($request->validated());

        return response()->json([
            'board_coin' => $boardCoin->fresh()->load('metalItem'),
            'message'    => 'تغییرات با موفقیت ذخیره شد.',
        ]);
    }

    public function updateSortOrder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:board_coins,id',
            'items.*.sort_order' => 'required|integer|min:1'
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->items as $item) {
                BoardCoin::where('id', $item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        $coins = BoardCoinService::getCoinsWithBuyAndSellPrices();

        BoardMetalPriceUpdated::dispatch($coins);

        return response()->json([
            'message' => 'ترتیب با موفقیت به‌روزرسانی شد',
            'success' => true
        ]);
    }

    public function destroy(BoardCoin $board_coin)
    {
        $board_coin->delete();

        return response()->json([
            'message' => 'سکه با موفقیت از تابلو حذف شد'
        ]);
    }
}
