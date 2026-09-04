<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshJibitToken;

class JibitController extends Controller
{
    public function refreshToken()
    {
        dispatch_sync(new RefreshJibitToken());
    }
}
