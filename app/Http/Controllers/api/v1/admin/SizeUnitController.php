<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SizeUnitStoreRequest;
use App\Models\Product;
use App\Models\SizeUnit;

class SizeUnitController extends Controller
{
    public function index()
    {
        $sizeUnits = SizeUnit::get();

        return \response()->json($sizeUnits);
    }

    public function store(SizeUnitStoreRequest $request)
    {
        $sizeUnit = SizeUnit::create($request->validated());

        return response()->json([
            'size_unit' => $sizeUnit,
            'message' => 'واحد سایز جدید با موفقیت ایجاد شد',
        ], 201);
    }

    public function usage($size_unit)
    {
        $usages = Product::where('size_unit_id', $size_unit)->count();

        return response()->json([
            'usages' => $usages,
        ]);
    }

    public function destroy(SizeUnit $size_unit)
    {
        $size_unit->delete();

        return response()->json([
            'message' => 'واحد سایز با موفقیت حذف شد'
        ]);
    }
}
