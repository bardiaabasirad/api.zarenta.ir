<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SelectedAutoOrderExchangeStoreRequest;
use App\Http\Requests\SelectedAutoOrderExchangeUpdateRequest;
use App\Models\SelectedAutoOrderExchange;

class SelectedAutoOrderExchangeController extends Controller
{
    public function update(SelectedAutoOrderExchangeUpdateRequest $request, SelectedAutoOrderExchange $selectedAutoOrderExchange)
    {
        $selectedAutoOrderExchange->update($request->validated());

        return response()->json([
            'message' => 'بروزرسانی با موفقیت انجام شد',
            'data' => $selectedAutoOrderExchange
        ]);
    }

    public function store(SelectedAutoOrderExchangeStoreRequest $request)
    {
        $selectedAutoOrderExchange = SelectedAutoOrderExchange::create($request->validated());

        return response()->json([
            'message' => 'صرافی با موفقیت به لیست اضافه شد',
            'data' => $selectedAutoOrderExchange->fresh()->load('autoOrderExchange')
        ]);
    }

    public function destroy(SelectedAutoOrderExchange $selectedAutoOrderExchange)
    {
        $selectedAutoOrderExchange->delete();

        return response()->json([
            'message' => 'صرافی با موفقیت از لیست حذف شد',
            'data' => $selectedAutoOrderExchange
        ]);
    }
}
