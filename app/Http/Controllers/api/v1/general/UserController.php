<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserInfoResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function info()
    {
        $user = User::select('id','full_name','phone','national_code','born_at')
            ->find(Auth::guard('user-api')->id());

        return response()->json(new UserInfoResource($user));
    }
}
