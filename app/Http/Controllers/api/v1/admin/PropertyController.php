<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PropertyStoreRequest;
use App\Http\Requests\PropertyUpdateRequest;
use App\Http\Requests\PropertyValueStoreRequest;
use App\Models\Property;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    public function index()
    {
        return response()->json(Property::withCount('products')->get());
    }

    public function show(Property $property)
    {
        return response()->json($property->loadCount('products'));
    }

    public function store(PropertyStoreRequest $request)
    {
        $property = new Property();
        $property->title = $request->title;
        $property->values = $request->values;
        $property->save();

        return response()->json([
            'property' => $property,
            'message' => 'ویژگی جدید با موفقیت ایجاد شد'
        ]);
    }

    public function update(PropertyUpdateRequest $request, Property $property)
    {
        $property->update($request->validated());

        return response()->json([
            'property' => $property->loadCount('products'),
            'message' => 'ویژگی با موفقیت بروزرسانی شد'
        ]);
    }

    public function storeNewValue(PropertyValueStoreRequest $request)
    {
        $property = Property::findOrFail($request->property_id);

        // Check if the new value already exists in the 'values' array
        if (in_array($request->value, $property->values)) {
            return response()->json([
                'errors' => [
                    'value' => ['مقدار انتخاب شده از قبل وجود دارد']
                ]
            ], 422);
        }

        // Add the new value to the 'values' array
        $updatedValues = $property->values;
        $updatedValues[] = $request->value;
        $property->values = $updatedValues;

        $property->save();

        return response()->json([
            'property' => $property->loadCount('products'),
            'message' => 'مقدار جدید با موفقیت ایجاد شد'
        ]);
    }

    public function remove(Property $property)
    {
        $property->delete();

        return response()->json([
            'message' => 'ویژگی با موفقیت حذف شد'
        ]);
    }

    public function usage($property)
    {
        $usages = DB::table('product_property')->where('property_id', $property)->count();

        return response()->json([
            'usages' => $usages,
        ]);
    }
}
