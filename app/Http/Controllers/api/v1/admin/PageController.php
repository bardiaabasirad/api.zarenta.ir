<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PageAboutUpdateRequest;
use App\Http\Requests\PageContactUpdateRequest;
use App\Http\Requests\PageTermsUpdateRequest;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class PageController extends Controller
{
    public function all()
    {
        return response()->json([
            'about' => [
                'page' => Page::where('slug','about')->firstOrFail()
            ],
            'contact' => [
                'page' => Page::where('slug','contact')->firstOrFail(),
                'settings' => Setting::whereIn('option_key', [
                    'support_center_contact_number','support_contact_number','instagram_channel','telegram_channel','rubika_channel','address'
                ])->get()
            ],
            'terms' => [
                'page' => Page::where('slug','terms')->firstOrFail()
            ],
        ]);
    }

    public function about(){
        return response()->json(Page::where('slug','about')->firstOrFail());
    }

    public function contact(){
        return response()->json([
            'page' => Page::where('slug','contact')->firstOrFail(),
            'settings' => Setting::whereIn('option_key', [
                'support_center_contact_number','support_contact_number','instagram_channel','telegram_channel','rubika_channel','address'
            ])->get()
        ]);
    }

    public function terms()
    {
        return response()->json(Page::where('slug', 'terms')->firstOrFail());
    }

    public function updateAbout(PageAboutUpdateRequest $request)
    {
        Page::where('slug','about')->update([
            'body' => $request->body
        ]);

        return response()->json([
            'message' => __('messages.the_about_page_has_been_successfully_updated'),
        ]);
    }

    public function updateContact(PageContactUpdateRequest $request)
    {
        Page::where('slug','contact')->update([
            'body' => $request->body
        ]);

        Cache::delete('contact_page');

        return response()->json([
            'message' => __('messages.the_contact_page_has_been_successfully_updated'),
        ]);
    }

    public function updateTerms(PageTermsUpdateRequest $request)
    {
        Page::where('slug', 'terms')->update([
            'body' => $request->body
        ]);

        Cache::delete('terms_page');

        return response()->json([
            'message' => __('messages.the_terms_page_has_been_successfully_updated'),
        ]);
    }
}
