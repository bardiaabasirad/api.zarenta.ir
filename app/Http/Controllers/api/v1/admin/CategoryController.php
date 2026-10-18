<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryStoreRequest;
use App\Http\Requests\CategoryUpdateRequest;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        // 1. اعتبارسنجی ورودی‌ها
        $validated = $request->validate([
            'sortBy'     => 'nullable|string|in:id,title,created_at,products_count',
            'dir'        => 'nullable|string|in:asc,desc',
            'count'      => 'nullable|integer|min:1|max:100',
            'code'       => 'nullable|integer',
            'title'      => 'nullable|string|max:100',
            'min'        => 'nullable|integer|min:0',
            'max'        => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'page'       => 'nullable|integer|min:1',
        ]);

        $perPage = (int) ($validated['count'] ?? 15);
        $sortBy  = $validated['sortBy'] ?? 'created_at';
        $dir     = strtolower($validated['dir'] ?? 'asc');

        // 2. ساخت کلید اختصاصی برای ردیس
        $queryParams = $request->query();
        ksort($queryParams);
        $cacheKey = 'list:' . md5(http_build_query($queryParams));

        // 3. کش در ردیس با استفاده از Cache Tags
        $categories = Cache::tags(['categories'])->remember($cacheKey, now()->addMinutes(30), function () use ($validated, $sortBy, $dir, $perPage) {
            return Category::query()
                ->select(['id', 'title', 'image', 'created_at'])
                ->withCount('products')
                ->when(!empty($validated['code']), fn($q) => $q->where('id', $validated['code']))
                ->when(!empty($validated['title']), fn($q) => $q->where('title', 'like', '%' . addcslashes($validated['title'], '%_') . '%'))
                ->when(!empty($validated['start_date']), fn($q) => $q->where('created_at', '>=', Carbon::parse($validated['start_date'])->startOfDay()))
                ->when(!empty($validated['end_date']), fn($q) => $q->where('created_at', '<=', Carbon::parse($validated['end_date'])->endOfDay()))
                ->when(isset($validated['min']), fn($q) => $q->has('products', '>=', (int) $validated['min']))
                ->when(isset($validated['max']), fn($q) => $q->has('products', '<=', (int) $validated['max']))
                ->orderBy($sortBy, $dir)
                ->paginate($perPage);
        });

        // 4. دریافت بیشترین تعداد محصول با ایمنی کامل در برابر جدول خالی
        // فقط یک عدد Integer را در ردیس کش می‌کنیم (بسیار سبک‌تر از کل شیء Model)
        $maxProductsCount = Cache::tags(['categories', 'products_stats'])->remember('max_products_count', now()->addHours(6), function () {
            return (int) (Category::withCount('products')
                ->orderByDesc('products_count')
                ->value('products_count') ?? 0);
        });

        return response()->json([
            'categories' => $categories,
            'category_with_most_products' => $maxProductsCount,
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
