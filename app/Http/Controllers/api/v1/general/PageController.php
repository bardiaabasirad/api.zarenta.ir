<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;

class PageController extends Controller
{
    public function terms()
    {
        $page = Cache::remember('terms_page', 60 * 24 * 30, function () {
            return Page::where('slug', 'terms')->firstOrFail();
        });

        return response()->json($page);
    }
}
