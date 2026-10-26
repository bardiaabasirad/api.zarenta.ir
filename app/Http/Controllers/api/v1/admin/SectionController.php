<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BannerStoreRequest;
use App\Http\Requests\SectionStoreRequest;
use App\Http\Requests\SectionToggleRequest;
use App\Http\Requests\SectionUpdateRequest;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Directory;
use App\Models\Product;
use App\Models\Section;
use App\Models\Slider;
use App\Models\Widget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SectionController extends Controller
{
    public function index()
    {
        $sections = Section::orderBy('order')->with('sectionable')->get();

        return response()->json($sections);
    }

    public function create()
    {
        return response()->json([
            'widgets' => Widget::whereNotIn('type', ['navbar', 'header', 'hero', 'footer'])
                ->whereNotIn('id', Section::where('sectionable_type', Widget::class)->pluck('sectionable_id'))
                ->get(),
            'categories' => Category::all(),
            'directories' => Directory::all(),
            'sliders' => Slider::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(SectionStoreRequest $request)
    {
        $footer = Section::where('sectionable_type', 'App\\Models\\Widget')
            ->with('sectionable')
            ->get()
            ->firstWhere(fn($section) => $section->sectionable->type === 'footer');

        $section = new Section();
        $section->fill([
            'created_by' => Auth::guard('admin-api')->id(),
            'sectionable_type' => $request->sectionable_type,
            'sectionable_id' => $request->sectionable_id ?? $this->createBanner($request)->id,
            'show_only_available_products' => $request->show_only_available_products ?? null,
            'show_on' => $request->show_on,
            'status' => 'active',
            'description' => $request->description,
            'order' => $section->order = $footer ? $footer->order : 1
        ]);
        $section->save();

        if ($footer) {
            $footer->increment('order');
        }

        return response()->json([
            'message' => 'بخش جدید با موفقیت ایجاد شد'
        ]);
    }

    public function update(SectionUpdateRequest $request, Section $section)
    {
        if ($section->sectionable_type === 'App\\Models\\Banner'){
            $section->load('sectionable');

            if ($section->sectionable_type !== $request->sectionable_type){
                // TODO:: Remove banner with images
            }
            else{

                $imagesCollection = collect($section->sectionable->images);

                $bannerData = collect($request->banner['images'])->map(function ($imageData, $index) use ($imagesCollection, $section, $request) {

                    if (isset($imageData['url'])){
                        return [
                            'id' => $imageData['id'],
                            'url' => $imageData['url'],
                            'action' => $imageData['action'],
                            'title' => $imageData['title'],
                            'target' => $imageData['target'],
                            'actionable' => $imageData['actionable'],
                        ];
                    }
                    else{

                        $found = $imagesCollection->firstWhere('id', $imageData['id']);
                        if ($found){
                            if (Storage::exists($found['url'])) {
                                Storage::delete($found['url']);
                            }
                        }

                        $file = $request->file("banner.images.$index.image");
                        if ($file) {

                            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                            $path = $file->storeAs('banners', $filename);

                            return [
                                'id' => $imageData['id'],
                                'url' => $path,
                                'action' => $imageData['action'],
                                'title' => $imageData['title'],
                                'target' => $imageData['target'],
                                'actionable' => $imageData['actionable'],
                            ];
                        }
                    }

                })->filter()->all();

                $banner = Banner::find($section->sectionable_id);

                if ($banner){
                    $banner->title = $request->banner['title'];
                    $banner->images = $bannerData;
                    if ($request->has('banner.layout')) {
                        $banner->layout = $this->normalizeBannerLayout($request->input('banner.layout'));
                    }
                    $banner->save();
                }

                $section->description = $request->description;
                $section->save();
            }
        }

        if ($request->has('sectionable_type')) $section->sectionable_type = $request->sectionable_type;
        if ($request->has('sectionable_id')) $section->sectionable_id = $request->sectionable_id;
        if ($request->has('show_on')) $section->show_on = $request->show_on;
        if ($request->has('show_only_available_products')) $section->show_only_available_products = $request->show_only_available_products;
        if ($request->has('description')) $section->description = $request->description;

        $section->save();

        return response()->json([
            'section' => $section->load(['sectionable','creator' => function ($query) {
                $query->select('id','full_name');
            }]),
            'message' => 'بخش با موفقیت بروزرسانی شد'
        ]);
    }

    public function toggle(SectionToggleRequest $request, Section $section)
    {
        $section->status = $request->status;
        $section->save();

        return response()->json([
            'message' => trans('messages.section_updated_successfully')
        ]);
    }

    public function addImage(Banner $banner, BannerStoreRequest $request)
    {
        $file = $request->file("image");
        $filename = uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('banners', $filename);

        $image = [
            'id' => $request->id,
            'url' => $path,
            'title' => $request->title,
            'action' => $request->action,
            'target' => $request->target,
            'actionable' => $request->actionable,
        ];

        // Retrieve the images as a collection, add the new image, and then set it back
        $images = collect($banner->images);
        $images->push($image);
        $banner->images = $images->all();

        $banner->save();

        return response()->json([
            'banner' => $banner,
        ]);
    }

    protected function createBanner($request)
    {
        $bannerData = collect($request->banner['images'])->map(function ($imageData, $index) use ($request) {

            $file = $request->file("banner.images.$index.image");

            if ($file) {
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('banners', $filename);

                return [
                    'id' => $imageData['id'],
                    'url' => $path,
                    'title' => $imageData['title'],
                    'action' => $imageData['action'],
                    'target' => $imageData['target'],
                    'actionable' => $imageData['actionable'],
                ];
            }
        })->filter()->all();

        $banner = new Banner();
        $banner->fill([
            'title' => $request->banner['title'],
            'images' => $bannerData,
            'layout' => $this->normalizeBannerLayout($request->input('banner.layout')),
        ]);
        $banner->save();

        return $banner;
    }

    protected function normalizeBannerLayout($layout): array
    {
        $defaults = Banner::DEFAULT_LAYOUT;
        if (!is_array($layout)) {
            return $defaults;
        }

        $mode = $layout['mode'] ?? $defaults['mode'];
        if (!in_array($mode, [Banner::MODE_IMAGE_LIST, Banner::MODE_BANNER], true)) {
            $mode = Banner::MODE_IMAGE_LIST;
        }

        return [
            'mode' => $mode,
            'show_title' => filter_var($layout['show_title'] ?? $defaults['show_title'], FILTER_VALIDATE_BOOLEAN),
            'show_image_text' => filter_var($layout['show_image_text'] ?? $defaults['show_image_text'], FILTER_VALIDATE_BOOLEAN),
            'rounded' => in_array($layout['rounded'] ?? '', [
                'rounded-none', 'rounded-sm', 'rounded', 'rounded-md', 'rounded-lg',
                'rounded-xl', 'rounded-2xl', 'rounded-3xl', 'rounded-full',
            ], true) ? $layout['rounded'] : $defaults['rounded'],
            'sizes' => [
                'sm' => $this->normalizeSizeClass($layout['sizes']['sm'] ?? $defaults['sizes']['sm']),
                'md' => $this->normalizeSizeClass($layout['sizes']['md'] ?? $defaults['sizes']['md']),
                'lg' => $this->normalizeSizeClass($layout['sizes']['lg'] ?? $defaults['sizes']['lg']),
            ],
            'padding_x' => [
                'sm' => $this->normalizePaddingClass($layout['padding_x']['sm'] ?? $defaults['padding_x']['sm']),
                'md' => $this->normalizePaddingClass($layout['padding_x']['md'] ?? $defaults['padding_x']['md']),
                'lg' => $this->normalizePaddingClass($layout['padding_x']['lg'] ?? $defaults['padding_x']['lg']),
            ],
        ];
    }

    protected function normalizeSizeClass(string $class): string
    {
        return in_array($class, Banner::SIZE_OPTIONS, true) ? $class : 'size-16';
    }

    protected function normalizePaddingClass(string $class): string
    {
        $allowed = ['px-0', 'px-2', 'px-4', 'px-6', 'px-8', 'px-10', 'px-12'];

        return in_array($class, $allowed, true) ? $class : 'px-4';
    }

    public function get(Section $section)
    {
        return response()->json([
            'section' => $section->load(['sectionable','creator' => function ($query) {
                $query->select('id','full_name');
            }]),
            'widgets' => Widget::whereNotIn('type', ['navbar', 'header', 'footer'])->get(),
            'categories' => Category::all(),
            'directories' => Directory::all(),
            'sliders' => Slider::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::active()->get(['id','title'])
        ]);
    }

    public function changeOrder(Request $request)
    {
        foreach ($request->all() as $item) {
            $section = Section::find($item['id']);
            $section->order = $item['order'];
            $section->save();
        }

        return response()->json([
            'message' => 'ترتیب بخش‌ها با موفقیت بروزرسانی شد'
        ]);
    }

    public function remove(Section $section)
    {
        if ($section->sectionable_type === 'App\\Models\\Banner'){
            $banner = Banner::find($section->sectionable_id);

            foreach ($banner->images as $image){
                if (Storage::exists($image['url'])) {
                    Storage::delete($image['url']);
                }
            }

            $banner->delete();
        }

        $section->delete();

        return response()->json([
            'message' => 'بخش با موفقیت حذف شد'
        ]);
    }

    public function removeImage($image_id)
    {
        $section_id = request()->input('section_id');

        $section = Section::find($section_id);

        if (! $section){
            return response()->json([
                'message' => 'بنر یافت نشد'
            ], 422);
        }

        $banner = Banner::find($section->sectionable_id);

        if (! $banner){
            return response()->json([
                'message' => 'بنر یافت نشد'
            ], 422);
        }

        $imagesCollection = collect($banner->images);

        $found = $imagesCollection->firstWhere('id', $image_id);

        if ($found){
            if (Storage::exists($found['url'])) {
                Storage::delete($found['url']);
            }
        }

        $banner->images = $imagesCollection->reject(function ($img) use ($image_id) {
            return $img['id'] == $image_id;
        })->values();

        $banner->save();

        return response()->json([
            'message' => 'تصویر با موفقیت حذف شد'
        ]);
    }
}
