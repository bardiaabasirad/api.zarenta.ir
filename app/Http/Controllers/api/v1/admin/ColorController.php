<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ColorStoreRequest;
use App\Models\Color;
use App\Models\Variety;

class ColorController extends Controller
{
    public function store(ColorStoreRequest $request)
    {
        $color = Color::create($request->validated());

        return response()->json([
            'color' => $color,
            'message' => trans('messages.a_new_color_has_been_created'),
        ], 201);
    }

    public function usage($color)
    {
        $usages = Variety::where('color_id', $color)->count();

        return response()->json([
            'usages' => $usages,
        ]);
    }

    public function destroy(Color $color)
    {
        $color->delete();

        return response()->json([
            'message' => trans('messages.color_removed_successfully')
        ]);
    }
}
