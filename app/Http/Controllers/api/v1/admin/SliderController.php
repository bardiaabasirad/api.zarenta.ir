<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSlideRequest;
use App\Http\Requests\StoreSliderRequest;
use App\Http\Requests\UpdateSliderRequest;
use App\Models\Slider;
use App\Models\Slide;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Illuminate\Http\Request;

class SliderController extends Controller
{
    public function index()
    {
        return response()->json([
            'sliders' => Slider::withCount('slides')->get()
        ]);
    }

    public function get(Request $request, Slider $slider): JsonResponse
    {
        // ۱. استخراج و تجزیه مقادیر include از Query String
        $includes = array_filter(explode(',', (string) $request->query('include', '')));

        // ۲. بررسی وجود slides و اعمال Lazy Eager Loading به همراه مرتب‌سازی
        if (in_array('slides', $includes, true)) {
            $slider->loadMissing([
                'slides' => function ($query) {
                    $query->orderBy('sort_order', 'asc');
                }
            ]);
        }

        // ۳. ارسال پاسخ به همراه داده‌های ساختاریافته
        return response()->json([
            'status' => 'success',
            'data'   => $slider,
        ]);
    }

    public function update(UpdateSliderRequest $request, Slider $slider): JsonResponse
    {
        $validated = $request->validated();

        // مسیر فایل‌هایی که در همین درخواست آپلود شده‌اند.
        // در صورت Rollback حذف خواهند شد.
        $newlyUploadedPaths = [];

        // مسیر تصاویر قدیمی که پس از Commit موفق حذف می‌شوند.
        $pathsToDeleteAfterCommit = [];

        $imageTypes = [
            'desktop' => [
                'file' => 'image_desktop',
                'path' => 'image_path_desktop',
            ],
            'tablet' => [
                'file' => 'image_tablet',
                'path' => 'image_path_tablet',
            ],
            'mobile' => [
                'file' => 'image_mobile',
                'path' => 'image_path_mobile',
            ],
        ];

        try {
            DB::transaction(function () use (
                $slider,
                $validated,
                $request,
                $imageTypes,
                &$newlyUploadedPaths,
                &$pathsToDeleteAfterCommit
            ) {
                /*
                |--------------------------------------------------------------------------
                | ۱. به‌روزرسانی اطلاعات اصلی اسلایدر
                |--------------------------------------------------------------------------
                */
                $slider->update([
                    'name' => $validated['name'],
                    'key' => $validated['key'],
                    'is_active' => filter_var(
                        $validated['is_active'],
                        FILTER_VALIDATE_BOOLEAN
                    ),
                ]);

                /*
                |--------------------------------------------------------------------------
                | ۲. حذف اسلایدهای علامت‌گذاری‌شده
                |--------------------------------------------------------------------------
                */
                if (!empty($validated['deleted_slide_ids'])) {
                    $slidesToDelete = Slide::where('slider_id', $slider->id)
                        ->whereIn('id', $validated['deleted_slide_ids'])
                        ->get();

                    foreach ($slidesToDelete as $slideToDelete) {
                        foreach ($imageTypes as $imageType) {
                            $oldPath = $slideToDelete->{$imageType['path']} ?? null;

                            if (!empty($oldPath)) {
                                $pathsToDeleteAfterCommit[] = $oldPath;
                            }
                        }

                        $slideToDelete->delete();
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | ۳. ایجاد یا به‌روزرسانی اسلایدها
                |--------------------------------------------------------------------------
                */
                if (!empty($validated['slides'])) {
                    foreach ($validated['slides'] as $index => $slideData) {
                        $slideId = $slideData['id'] ?? null;

                        /** @var Slide|null $slide */
                        $slide = $slideId
                            ? Slide::where('slider_id', $slider->id)
                                ->find($slideId)
                            : null;

                        if (!$slide) {
                            $slide = new Slide([
                                'slider_id' => $slider->id,
                            ]);
                        }

                        $imagePaths = [];

                        /*
                        |--------------------------------------------------------------------------
                        | پردازش تصاویر Desktop / Tablet / Mobile
                        |--------------------------------------------------------------------------
                        */
                        foreach ($imageTypes as $imageType) {
                            $fileField = $imageType['file'];
                            $pathField = $imageType['path'];

                            // مسیر فعلی تصویر در دیتابیس
                            $currentPath = $slide->{$pathField} ?? null;

                            // در صورت عدم ارسال مسیر جدید، مسیر قبلی حفظ می‌شود.
                            $imagePath = $slideData[$pathField] ?? $currentPath;

                            $fileKey = "slides.{$index}.{$fileField}";

                            if ($request->hasFile($fileKey)) {
                                $file = $request->file($fileKey);

                                $extension = strtolower(
                                    $file->getClientOriginalExtension()
                                );

                                $filename = uniqid('', true) . '.' . $extension;

                                $newImagePath = $file->storeAs(
                                    'slides',
                                    $filename
                                );

                                if (!$newImagePath) {
                                    throw new \RuntimeException(
                                        "ذخیره فایل {$fileField} با خطا مواجه شد."
                                    );
                                }

                                // ثبت فایل جدید برای حذف در صورت Rollback
                                $newlyUploadedPaths[] = $newImagePath;

                                // تصویر قدیمی پس از Commit موفق حذف می‌شود.
                                if (
                                    !empty($currentPath) &&
                                    $currentPath !== $newImagePath
                                ) {
                                    $pathsToDeleteAfterCommit[] = $currentPath;
                                }

                                $imagePath = $newImagePath;
                            }

                            $imagePaths[$pathField] = $imagePath;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | پاک‌سازی اطلاعات Action
                        |--------------------------------------------------------------------------
                        */
                        $actionType = $slideData['action_type'] ?? 'none';

                        $actionValue = $actionType === 'none'
                            ? null
                            : ($slideData['action_value'] ?? null);

                        $actionText = $actionType === 'none'
                            ? null
                            : ($slideData['action_text'] ?? null);

                        /*
                        |--------------------------------------------------------------------------
                        | ذخیره اطلاعات اسلاید
                        |--------------------------------------------------------------------------
                        */
                        $slide->fill([
                            'title' => $slideData['title'] ?? null,
                            'subtitle' => $slideData['subtitle'] ?? null,

                            'image_path_desktop' =>
                                $imagePaths['image_path_desktop'] ?? null,

                            'image_path_tablet' =>
                                $imagePaths['image_path_tablet'] ?? null,

                            'image_path_mobile' =>
                                $imagePaths['image_path_mobile'] ?? null,

                            'link_url' => $slideData['link_url'] ?? null,

                            'action_type' => $actionType,
                            'action_value' => $actionValue,
                            'action_text' => $actionText,

                            'sort_order' => (int) (
                                $slideData['sort_order'] ?? 0
                            ),

                            'is_active' => filter_var(
                                $slideData['is_active'] ?? false,
                                FILTER_VALIDATE_BOOLEAN
                            ),
                        ]);

                        $slide->save();
                    }
                }
            });

            /*
            |--------------------------------------------------------------------------
            | حذف تصاویر قدیمی پس از Commit موفق
            |--------------------------------------------------------------------------
            |
            | حذف فایل‌ها داخل تراکنش انجام نمی‌شود؛ زیرا عملیات Storage
            | قابل Rollback نیست.
            |--------------------------------------------------------------------------
            */
            $pathsToDeleteAfterCommit = array_values(
                array_unique($pathsToDeleteAfterCommit)
            );

            foreach ($pathsToDeleteAfterCommit as $oldPath) {
                try {
                    if (Storage::exists($oldPath)) {
                        Storage::delete($oldPath);
                    }
                } catch (\Throwable $cleanupException) {
                    // خطای حذف فایل قدیمی نباید نتیجه تراکنش را Rollback کند.
                    report($cleanupException);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | بارگذاری مجدد اسلایدر و اسلایدها
            |--------------------------------------------------------------------------
            */
            $slider->load([
                'slides' => function ($query) {
                    $query->orderBy('sort_order', 'asc');
                },
            ]);

            return response()->json([
                'success' => true,
                'message' => 'اسلایدر و اسلایدها با موفقیت به‌روزرسانی شدند.',
                'data' => $slider,
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            /*
            |--------------------------------------------------------------------------
            | حذف فایل‌های جدید در صورت بروز خطا یا Rollback
            |--------------------------------------------------------------------------
            */
            foreach (array_unique($newlyUploadedPaths) as $newPath) {
                try {
                    if (Storage::exists($newPath)) {
                        Storage::delete($newPath);
                    }
                } catch (\Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'خطایی در به‌روزرسانی اطلاعات رخ داد. لطفاً مجدداً تلاش کنید.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function storeSlide(StoreSlideRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $uploadedPath = null;

        try {
            // ۱. ذخیره تصویر با متد storeAs و ایجاد نام یکتا با uniqid() طبق ساختار مد نظر شما
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();

                // ذخیره در پوشه slides
                $uploadedPath = $file->storeAs('slides', $filename);
            }

            // ۲. ثبت اطلاعات در دیتابیس
            $slide = DB::transaction(function () use ($validated, $uploadedPath) {
                if ($validated['action_type'] === 'none') {
                    $validated['action_value'] = null;
                    $validated['action_text'] = null;
                }

                $slideData = array_merge($validated, [
                    'image_path' => $uploadedPath,
                ]);

                unset($slideData['image']);

                return Slide::create($slideData);
            });

            $slide->load('slider:id,name,key');

            return response()->json([
                'success' => true,
                'message' => 'اسلاید با موفقیت اضافه شد.',
                'data'    => $slide,
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            // در صورت بروز خطای دیتابیس، تصویر آپلود شده حذف می‌شود تا فایل هرز نماند
            if ($uploadedPath && Storage::exists($uploadedPath)) {
                Storage::delete($uploadedPath);
            }

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'خطایی در ثبت اطلاعات رخ داد. لطفاً مجدداً تلاش کنید.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreSliderRequest $request): JsonResponse
    {
        try {
            $slider = Slider::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'اسلایدر جدید با موفقیت ساخته شد.',
                'data'    => $slider,
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'خطایی در ثبت اسلایدر رخ داد.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
