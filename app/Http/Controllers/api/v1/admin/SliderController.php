<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSlideRequest;
use App\Models\Slider;
use App\Models\Slide;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SliderController extends Controller
{
    public function index()
    {
        return response()->json([
            'sliders' => Slider::all()
        ]);
    }

    public function store(StoreSlideRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $uploadedPath = null;

        try {
            // ۱. ذخیره‌سازی تصویر در دیسک پابلیک
            // فایل در مسیر storage/app/public/slides ذخیره می‌شود
            if ($request->hasFile('image')) {
                $uploadedPath = $request->file('image')->store('slides', 'public');
            }

            // ۲. ثبت امن اطلاعات داخل Transaction دیتابیس
            $slide = DB::transaction(function () use ($validated, $uploadedPath) {
                // اگر نوع اکشن none بود، مقدار و متن اکشن خالی بماند
                if ($validated['action_type'] === 'none') {
                    $validated['action_value'] = null;
                    $validated['action_text'] = null;
                }

                // آماده‌سازی فیلدها برای Mass Assignment
                $slideData = array_merge($validated, [
                    'image_path' => $uploadedPath,
                ]);

                // حذف کلید تصویر از آرایه داده‌های جدول چون نام ستون image_path است
                unset($slideData['image']);

                return Slide::create($slideData);
            });

            // بارگذاری رابطه برای پاسخ کامل‌تر به Angular
            $slide->load('slider:id,name,key');

            return response()->json([
                'success' => true,
                'message' => 'اسلاید با موفقیت اضافه شد.',
                'data'    => $slide,
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            // Rollback فایل آپلود شده در صورت خطای دیتابیس تا هاست پر از فایل‌های بی‌استفاده نشود
            if ($uploadedPath && Storage::disk('public')->exists($uploadedPath)) {
                Storage::disk('public')->delete($uploadedPath);
            }

            report($e); // لاگ خطا در laravel.log

            return response()->json([
                'success' => false,
                'message' => 'خطایی در ثبت اطلاعات رخ داد. لطفاً مجدداً تلاش کنید.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
