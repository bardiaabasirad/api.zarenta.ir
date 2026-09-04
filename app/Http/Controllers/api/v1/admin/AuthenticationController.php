<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthenticationController extends Controller
{
    public function login(AdminLoginRequest $request)
    {
        // phone is modified on LoginRequest by prepareForValidation method
        $admin = Admin::firstWhere('phone', $request->input('phone'));

        if (is_null($admin)) {
            return \response()->json([
                'errors'=> [
                    'phone' => ['نام کاربری یا کلمه عبور اشتباه است.']
                ]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! Hash::check($request->input('password'), $admin->password)) {
            return response()->json([
                'errors'=> [
                    'phone' => ['نام کاربری یا کلمه عبور اشتباه است.']
                ]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $admin->isActive()) {
            return \response()->json([
                'message' => 'حساب کاربری شما غیر فعال شده است'
            ], Response::HTTP_FORBIDDEN);
        }

        $token = $admin->createToken($request->device_name??'Unknown device');

        $admin->update([
            'last_login_at' => Carbon::now(),
            'last_login_ip' => $request->ip(),
        ]);

        return \response()->json([
            'token' => $token->plainTextToken,
            'admin' => $admin,
            'permissions' => $admin->allPermissions(),
            'message' => 'خوش آمدید',
        ], Response::HTTP_CREATED);
    }

    public function logout()
    {
        // Delete the current access token
        auth('admin-api')->user()->currentAccessToken()->delete();

        // Return the response
        return \response()->json(null,Response::HTTP_NO_CONTENT);
    }
}
