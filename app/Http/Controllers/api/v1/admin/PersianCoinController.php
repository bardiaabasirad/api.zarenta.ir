<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PersianCoinStoreRequest;
use App\Http\Requests\PersianCoinUpdateRequest;
use App\Models\PersianCoin;

class PersianCoinController extends Controller
{
    public function index()
    {
        return response()->json([
            'persian_coin' => PersianCoin::orderBy('weight')->get(),
        ]);
    }

    public function store(PersianCoinStoreRequest $request)
    {
        return response()->json([
            'persian_coin' => PersianCoin::create($request->validated()),
            'message' => 'سکه جدید با موفقیت ایجاد شد',
        ], 201);
    }

    public function update(PersianCoinUpdateRequest $request, PersianCoin $coin)
    {
        $coin->update($request->validated());

        return response()->json([
            'persian_coin' => $coin,
            'message' => 'سکه با موفقیت بروزرسانی شد'
        ]);
    }

    public function destroy(PersianCoin $coin)
    {
        $coin->delete();

        return response()->json([
            'message' => 'سکه با موفقیت حذف شد'
        ]);
    }
}
