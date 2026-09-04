<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryStoreRequest;
use App\Http\Requests\CategoryUpdateRequest;
use App\Models\Category;
use Carbon\Carbon;

class CategoryController extends Controller
{
    public function index()
    {
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');
        $code = request()->input('code');
        $title = request()->input('title');
        $min = request()->input('min');
        $max = request()->input('max');
        $start_date = request()->input('start_date');
        $end_date = request()->input('end_date');

        $categories = Category::query();

        $categories = $categories->select('id','title','image','created_at')
            ->when(isset($code), function ($query) use ($code){
                $query->where('id', 'like', '%' .$code . '%');
            })
            ->when(isset($title), function ($query) use ($title){
                $query->where('title', 'like', '%' . $title . '%');
            })
            ->when(isset($start_date) && isset($end_date), function ($query) use ($start_date, $end_date){
                $startDate = Carbon::parse($start_date)->startOfDay();
                $endDate = Carbon::parse($end_date)->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->when(isset($min), function ($query) use ($min) {
                $query->having('products_count', '>=', $min);
            })
            ->when(isset($max), function ($query) use ($max) {
                $query->having('products_count', '<=', $max);
            })
            ->withCount(['products as products_count'])
            ->orderBy($sortBy??'created_at', $dir??'asc')->paginate($count);

        $categoryWithMostProducts = Category::withCount('products')
            ->orderBy('products_count', 'desc')
            ->first();

        return response()->json([
            'categories' => $categories,
            'category_with_most_products' => $categoryWithMostProducts->products_count
        ]);
    }

    public function store(CategoryStoreRequest $request)
    {
        $image = $request->file('image');
        // Generate a unique file name
        $filename = uniqid() . '.' . $image->getClientOriginalExtension();
        // Move the file to the public categories directory
        $path = $image->storeAs('categories', $filename);

        $category = new Category();
        $category->slug = $request->slug;
        $category->title = $request->title;
        $category->image = $path;
        $category->save();

        return response()->json([
            'message' => trans('messages.a_new_category_has_been_created')
        ], 201);
    }

    public function update(CategoryUpdateRequest $request, Category $category)
    {
        if ($request->hasFile('image')) {
            $image = $request->file('image');

            // Generate a unique file name
            $filename = uniqid() . '.' . $image->getClientOriginalExtension();

            // Move the file to the public products directory
            $path = $image->storeAs('categories', $filename);

            $category->image = $path;
        }
        if ($request->title) $category->title = $request->title;

        $category->save();

        return response()->json([
            'category' => $category->loadCount(['products as products_count']),
            'message' => trans('messages.the_category_has_been_successfully_updated')
        ]);
    }

    public function show(Category $category)
    {
        $category->loadCount(['products as products_count']);
        return response()->json([
            'category' => $category
        ]);
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json([
            'message' => trans('messages.the_category_has_been_successfully_removed')
        ]);
    }
}
