<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserAvatarUploadRequest;
use App\Http\Resources\UserInfoResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function info()
    {
        $user = User::select('id','full_name','phone','national_code','born_at','avatar')
            ->find(Auth::guard('user-api')->id());

        return response()->json(new UserInfoResource($user));
    }

    public function uploadAvatar(UserAvatarUploadRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('user-api')->user();

        $previousAvatar = $user->avatar;
        $path = Storage::put('users', $request->file('image'));

        $user->update(['avatar' => $path]);

        if ($previousAvatar && Storage::exists($previousAvatar)) {
            Storage::delete($previousAvatar);
        }

        return response()->json([
            'avatar' => $path,
            'message' => 'تصویر پروفایل با موفقیت به‌روزرسانی شد',
        ]);
    }

    public function deleteAvatar(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('user-api')->user();

        $previousAvatar = $user->avatar;

        if ($previousAvatar) {
            $user->update(['avatar' => null]);

            if (Storage::exists($previousAvatar)) {
                Storage::delete($previousAvatar);
            }
        }

        return response()->json([
            'avatar' => null,
            'message' => 'تصویر پروفایل حذف شد',
        ]);
    }
}
