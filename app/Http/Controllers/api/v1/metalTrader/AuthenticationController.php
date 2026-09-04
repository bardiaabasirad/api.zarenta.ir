<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Http\Requests\MetalTraderChangePasswordByProfileRequest;
use App\Http\Requests\MetalTraderChangePasswordRequest;
use App\Http\Requests\MetalTraderCompleteProfileRequest;
use App\Http\Requests\MetalTraderLoginByPasswordRequest;
use App\Http\Requests\MetalTraderLoginRequest;
use App\Http\Requests\MetalTraderReviewRequest;
use App\Http\Requests\MetalTraderVerifyPhoneRequest;
use App\Http\Resources\ClientInfoResource;
use App\Models\MetalTrader;
use App\Services\MetalTraderLeadService;
use App\Services\MetalTraderService;
use App\Services\PhoneVerificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationController extends Controller
{
    public function otpSend(MetalTraderLoginRequest $request)
    {
        $phone = $request->input('phone');
        $isForgotPassword = $request->input('action') === 'forgot_password';
        $action = $isForgotPassword ? 'forgot_password' : 'otp';

        $metalTrader = MetalTrader::where('phone', $phone)->first();

        if (!$isForgotPassword) {
            $hasPassword = $metalTrader && !empty($metalTrader->password);

            if ($hasPassword && !$request->boolean('force_otp')) {
                return response()->json([
                    'action' => 'password',
                    'title' => 'ورود با رمز عبور',
                    'message' => 'لطفاً رمز عبور خود را وارد کنید.',
                ]);
            }
        }

        if (!$metalTrader) {
            app(MetalTraderLeadService::class)->track($phone);
        }

        if ($secondsRemaining = PhoneVerificationService::isCodeSentRecently(MetalTrader::class, $phone)) {
            return response()->json([
                'action' => $action,
                'time_remaining' => $secondsRemaining,
                'title' => 'کد تایید قبلاً ارسال شده است',
                'message' => 'کد احراز هویت قبلاً به شماره شما ارسال شده است.',
            ]);
        }

        try {
            PhoneVerificationService::sendCode(MetalTrader::class, $phone, 'utq9vazla5x0uqn');

            return response()->json([
                'action' => $action,
                'time_remaining' => 120,
                'title' => 'کد تایید ارسال شد',
                'message' => 'کد احراز هویت به شماره شما ارسال شد.',
            ]);
        } catch (\Exception $exception) {
            return response()->json(['message' => 'ارسال کد با خطا مواجه شد.'], 500);
        }
    }

    public function passwordLogin(MetalTraderLoginByPasswordRequest $request)
    {
        $phone = $request->input('phone');
        $metalTrader = MetalTrader::where('phone', $phone)->withHasPass()->first();

        // ۱. بررسی وجود کاربر و صحت رمز عبور (جلوگیری از خطای null)
        if (!$metalTrader || !$metalTrader->password || !Hash::check($request->password, $metalTrader->password)) {
            return response()->json([
                'message' => 'اطلاعات وارد شده صحیح نیست.',
                'errors' => ['password' => ['رمز عبور یا شماره تلفن اشتباه است.']]
            ], 401);
        }

        // ۲. وضعیت غیرفعال
        if ($metalTrader->status === 'inactive') {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['status' => ['حساب کاربری شما غیر فعال می‌باشد']],
            ], 422);
        }

        // ۳. وضعیت در انتظار تایید
        if ($metalTrader->status === 'pending') {
            return response()->json([
                'title' => 'حساب شما در صف تأیید قرار دارد',
                'message' => 'اطلاعات شما قبلاً دریافت شده و در انتظار تأیید مدیریت است.',
                'user_status' => 'pending',
            ], 200);
        }

        $token = $metalTrader->createToken($request->input('device_name', 'Unknown device'));

        $metalTrader->load('dealingGroup');
        $metalTrader->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip()
        ]);
        $metalTrader->inquiry_access = $metalTrader->subscriptionNotExpired('inquiry');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new ClientInfoResource($metalTrader)
        ]);
    }

    public function verify(MetalTraderVerifyPhoneRequest $request, MetalTraderService $metalTraderService)
    {
        $code = $request->string('verification_code')->toString();
        $phone = $request->string('phone')->toString();
        $action = $request->input('action', 'login');

        $verificationCode = PhoneVerificationService::checkVerificationCodeIsValid(
            MetalTrader::class,
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
            $metalTrader = MetalTrader::where('phone', $phone)->withHasPass()->first();

            // ─── کاربر جدید ───────────────────────────────────────────────
            if (!$metalTrader) {
                MetalTrader::create([
                    'phone' => $phone,
                    'api_key' => $metalTraderService->generateApiKey(),
                    'status' => 'pre_registered',
                ]);

                app(MetalTraderLeadService::class)->remove($phone);

                DB::commit();

                return response()->json([
                    'next_action' => 'complete-profile',
                ], Response::HTTP_OK);
            }

            // ─── حساب در انتظار تأیید مدیر ───────────────────────────────
            if (in_array($metalTrader->status, ['pre_registered', 'pending'])) {
                DB::rollBack();

                return response()->json([
                    'next_action' => 'pending',
                ], Response::HTTP_OK);
            }

            // ─── حساب غیرفعال ─────────────────────────────────────────────
            if ($metalTrader->status === 'inactive') {
                DB::rollBack();
                return response()->json([
                    'message' => 'حساب کاربری شما غیرفعال است. برای فعال‌سازی با پشتیبانی تماس بگیرید.',
                ], Response::HTTP_FORBIDDEN);
            }

            // ─── حساب رد شده ─────────────────────────────────────────────
            if ($metalTrader->status === 'rejected') {
                $resetToken = $metalTrader->createToken(
                    'request-review',
                    ['request-review']
                );

                DB::commit();

                return response()->json([
                    'next_action' => 'rejected-request',
                    'review_token' => $resetToken->plainTextToken,
                ], Response::HTTP_OK);
            }

            // ─── بازیابی رمز عبور ─────────────────────────────────────────
            if ($action === 'forgot_password') {
                $resetToken = $metalTrader->createToken(
                    'reset-password',
                    ['reset-password']
                );

                DB::commit();

                return response()->json([
                    'next_action' => 'reset-credentials',
                    'reset_token' => $resetToken->plainTextToken,
                ], Response::HTTP_OK);
            }

            // ─── لاگین موفق ───────────────────────────────────────────────
            $token = $metalTrader->createToken($request->input('device_name', 'Unknown device'));

            $metalTrader->load('dealingGroup');
            $metalTrader->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip()
            ]);
            $metalTrader->inquiry_access = $metalTrader->subscriptionNotExpired('inquiry');

            DB::commit();

            return response()->json([
                'next_action' => 'home',
                'token' => $token->plainTextToken,
                'user' => new ClientInfoResource($metalTrader)
            ], Response::HTTP_CREATED);

        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function completeProfile(MetalTraderCompleteProfileRequest $request)
    {
        MetalTrader::where('phone', $request->input('phone'))->update([
            'status' => 'pending',
            'name' => $request->input('full_name'),
            'business_type' => $request->input('business_type'),
        ]);

        return response()->json([
            'next_action' => 'pending',
        ], Response::HTTP_OK);
    }

    public function changePassword(
        MetalTraderChangePasswordRequest $request
    ) {
        /** @var MetalTrader $metalTrader */
        $metalTrader = $request->user();

        $metalTrader->update([
            'password' => Hash::make($request->input('password')),
            'last_login_at' => Carbon::now(),
            'last_login_ip' => $request->ip(),
        ]);

        $request->user()->currentAccessToken()?->delete();

        $token = $metalTrader->createToken($request->input('device_name', 'Unknown device'));

        $metalTrader->load('dealingGroup');
        $metalTrader->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip()
        ]);
        $metalTrader->inquiry_access = $metalTrader->subscriptionNotExpired('inquiry');

        return response()->json([
            'next_action' => 'home',
            'token' => $token->plainTextToken,
            'user' => new ClientInfoResource($metalTrader),
        ]);
    }

    public function changePasswordByProfile(
        MetalTraderChangePasswordByProfileRequest $request
    ) {
        /** @var MetalTrader $metalTrader */
        $metalTrader = $request->user();

        $metalTrader->update(['password' => $request->validated('password')]);

//        // ابطال سایر نشست‌ها (Sanctum) — توکن فعلی حفظ می‌شود
//        $metalTrader->tokens()
//            ->when(
//                $metalTrader->currentAccessToken(),
//                fn ($query, $token) => $query->whereKeyNot($token->getKey())
//            )
//            ->delete();

        return response()->json([
            'message' => 'رمز عبور با موفقیت تغییر کرد.',
        ]);
    }


    public function submitReevaluationRequest(MetalTraderReviewRequest $request)
    {
        $metalTrader = $request->user();

        $metalTrader->update([
           'status' => 'pending',
        ]);
    }

    public function logout()
    {
        auth('metal-trader-api')->user()->currentAccessToken()->delete();
        return response()->json('با موفقیت خارج شدید');
    }
}
