<?php

namespace App\Http\Controllers\api\v1\general;

use App\Enums\UserStatus;
use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\SignupRequest;
use App\Http\Requests\VerifyPhoneRequest;
use App\Http\Resources\UserInfoResource;
use App\Models\User;
use App\Services\CartService;
use App\Services\JibitService;
use App\Services\PhoneVerificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\CalendarUtils;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;

class AuthenticationController extends Controller
{

    public function __construct()
    {
        $this->middleware(\App\Http\Middleware\LimitNationalCodeValidation::class)->only('fetchMatchingData');
    }

    public function login(LoginRequest $request)
    {
        if ($secondsRemaining = PhoneVerificationService::isCodeSentRecently(User::class, $request->input('phone'))) {
            return response()->json([
                'time_remaining' => $secondsRemaining,
            ]);
        }

        PhoneVerificationService::sendCode(User::class, $request->input('phone'));

        if(! User::where('phone', $request->input('phone'))->exists()){
            User::create([
                'phone' => $request->input('phone')
            ]);
        }

        return response()->json([
            'message' => 'کد تایید ارسال شد',
            'time_remaining' => PhoneVerificationService::isCodeSentRecently(User::class, $request->input('phone'))
        ]);
    }

    public function verify(VerifyPhoneRequest $request)
    {
        $code = $request->string('verification_code')->toString();
        $phone = $request->string('phone')->toString();
        $verificationCode =
            PhoneVerificationService::checkVerificationCodeIsValid(
                User::class,
                $phone,
                $code
            );

        if (!$verificationCode) {
            throw ValidationException::withMessages([
                'verification_code' => ['کد تایید نامعتبر یا منقضی شده است.'],
            ]);
        }

        $verificationCode->use();

        DB::beginTransaction();

        try {
            $user = User::select('id', 'full_name', 'phone', 'national_code', 'born_at', 'status')
                ->where('phone', $phone)
                ->whereNotIn('status', [UserStatus::INITIAL, UserStatus::INCOMPLETE])
                ->first();

            if (is_null($user)) {
                User::where('phone', $phone)
                    ->where('status', UserStatus::INITIAL)
                    ->update(['status' => UserStatus::INCOMPLETE]);

                DB::commit();

                return response()->json(['status' => 'new-user'], Response::HTTP_ACCEPTED);
            }

            if ($request->filled('items')) {
                $cartItems = CartService::syncCart(
                    $user,
                    $request->input('items') ?? []
                );
            }

            $deviceName = $request->string('device_name')->trim()->toString();

            if ($deviceName === '') {
                $deviceName = $request->userAgent() ?: 'Unknown device';
            }

            $token = $user->createToken($deviceName);
            $user->update([
                'last_login_at' => Carbon::now(),
                'last_login_ip' => $request->ip()
            ]);

            DB::commit();

            if ($user->status == UserStatus::ACTIVE) {
                return response()->json([
                    'token' => $token->plainTextToken,
                    'user' => new UserInfoResource($user),
                    'cart_items' => $cartItems ?? null,
                ], Response::HTTP_CREATED);
            } else {
                return response()->json(['status' => 'inactive'], 422);
            }
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function signup(SignupRequest $request)
    {
        try {
            $user = User::where('phone', $request->input('phone'))
                ->where('status', '!=', UserStatus::INCOMPLETE)->first();

            if (empty($user)) {

                if (App::environment('local')) {
                    $user = User::where('phone', $request->input('phone'))->first();

                    $user->update([
                        'full_name' => $request->input('full_name'),
                        'national_code' => $request->national_code,
                        'born_at' => $request->born_at,
                        'status' => UserStatus::ACTIVE,
                    ]);

                    $cartItems = CartService::syncCart(
                        $user,
                        $request->input('items') ?? []
                    );

                    $token = $user->createToken($request->device_name??'Unknown device');

                    DB::commit();

                    event(new UserRegistered($user));

                    return \response()->json([
                        'token' => $token->plainTextToken,
                        'user' => new UserInfoResource($user),
                        'cart_items' => $cartItems,
                    ],Response::HTTP_CREATED);
                }

                $object = (object)[
                    'mobile_number' => $request->phone,
                    'national_code' => $request->national_code,
                ];

                $response = JibitService::matching($object);

                switch ($response['code']){
                    case 'valid':
                        DB::beginTransaction();

                        // Extract user by national code where phone does not match the request input
                        $user = User::where('phone', '!=', $request->input('phone'))
                            ->where('national_code', $request->input('national_code'))
                            ->first();

                        if ($user) {
                            // Mask the phone number
                            $number = maskPhoneNumber($user->phone);

                            // Return the error response
                            return response()->json([
                                'errors' => [
                                    'national_code' => ["لطفا با شماره تلفنی که قبلا ثبت نام کرده‌اید {$number} وارد شوید"]
                                ]
                            ], 422);
                        }

                        $user = User::where('phone', $request->input('phone'))->first();

                        $user->update([
                            'full_name' => $request->input('full_name'),
                            'national_code' => $request->national_code,
                            'born_at' => $request->born_at,
                            'status' => UserStatus::ACTIVE,
                            'last_login_at' => Carbon::now(),
                            'last_login_ip' => $request->ip(),
                        ]);

                        $cartItems = CartService::syncCart(
                            $user,
                            $request->input('items') ?? []
                        );

                        $token = $user->createToken($request->device_name??'Unknown device');

                        DB::commit();

                        event(new UserRegistered($user));

                        return \response()->json([
                            'token' => $token->plainTextToken,
                            'user' => new UserInfoResource($user),
                            'cart_items' => $cartItems,
                        ],Response::HTTP_CREATED);
                    case 'not_valid':
                        return \response()->json([
                            'errors' => [
                                'national_code' => ['عدم تطابق کد ملی و شماره موبایل']
                            ]
                        ], 422);
                    case 'invalid.request_body':
                        return \response()->json([
                            'errors' => [
                                'server_error' => ['invalid_request_body']
                            ]
                        ], 422);
                    case 'nationalCode.not_valid':
                        return \response()->json([
                            'errors' => [
                                'national_code' => ['کد ملی وارد شده معتبر نیست']
                            ]
                        ], 422);
                    case 'mobileNumber.not_valid':
                        return \response()->json([
                            'errors' => [
                                'national_code' => ['شماره موبایل وارد شده معتبر نیست، لطفا شماره موبایل خود را با دقت وارد کنید']
                            ]
                        ], 422);
                    case 'daily_limit.reached':
                        return \response()->json([
                            'errors' => [
                                'server_error' => ['ما به محدودیت روزانه استعلام کد ملی رسیده‌ایم لطفا روز آینده برای ثبت نام مجدد اقدام بفرمایید']
                            ]
                        ], 422);
                    case 'forbidden':
                        return \response()->json([
                            'errors' => [
                                'server_error' => ['forbidden']
                            ]
                        ], 422);
                    case 'server.error':
                        return \response()->json([
                            'errors' => [
                                'server_error' => ['server_error']
                            ]
                        ], 422);
                    case 'providers.not_available':
                        return \response()->json([
                            'errors' => [
                                'server_error' => ['در حال حاضر سرویس دهنده در دسترس نیست']
                            ]
                        ], 422);
                    case 'handler.found_not':
                        return \response()->json([
                            'errors' => [
                                'server_error' => ['handler_not_found']
                            ]
                        ], 422);
                    case 'curl_error':
                        return \response()->json([
                            'errors' => [
                                'server_error' => ['با توجه به اختلال در سامانه استعلام کد ملی لطفا بعداً تلاش کنید']
                            ]
                        ], 422);
                }
            }
            else {
                return \response()->json([
                    'errors' => [
                        'national_code' => ['شما قبلاً ثبت نام کرده‌اید، لطفا از طریق صفحه ورود اقدام فرمایید']
                    ]
                ], 422);
            }
        }
        catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function logout()
    {
        auth('user-api')->user()->currentAccessToken()->delete();
        return response()->json('شما با موفقیت خارج شدید');
    }
}
