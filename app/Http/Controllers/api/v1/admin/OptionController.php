<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OptionStoreRequest;
use App\Http\Requests\OptionUpdateRequest;
use App\Models\Option;

class OptionController extends Controller
{
    public function index()
    {
        $increaseReasons = Option::where('key', 'increase_inventory_count')->get();
        $decreaseReasons = Option::where('key', 'decrease_inventory_count')->get();
        return response()->json([
            'increase_reasons' => $increaseReasons,
            'decrease_reasons' => $decreaseReasons,
        ]);
    }

    public function store(OptionStoreRequest $request)
    {
        $option = Option::create($request->validated());

        return response()->json([
            'option' => $option,
            'message' => trans('messages.a_new_option_has_been_created'),
        ], 201);
    }

    public function update(OptionUpdateRequest $request, Option $option)
    {
        $option->update($request->validated());

        return response()->json([
            'message' => trans('messages.the_option_has_been_successfully_updated'),
        ]);
    }

    public function delete(Option $option)
    {
        $option->delete();

        return response()->json([
            'message' => 'علت با موفقیت حذف شد',
        ]);
    }
}
