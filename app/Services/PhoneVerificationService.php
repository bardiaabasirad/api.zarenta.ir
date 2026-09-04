<?php

namespace App\Services;

use App\Jobs\SmsJob;
use App\Models\VerificationCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PhoneVerificationService
{
    private const MAX_FAILED_ATTEMPTS = 5;

    public static function isCodeSentRecently($type, $phone)
    {
        $latestVerificationCode = VerificationCode::where('phone', $phone)
            ->where('authenticatable_type', $type)
            ->where('created_at', '>=', now()->subMinutes(config('app.code_interval')))
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (is_null($latestVerificationCode)) {
            return false;
        }

        return ceil(
            now()->diffInSeconds(
                Carbon::parse($latestVerificationCode->created_at)
                    ->addMinutes((int) config('app.code_interval'))
            )
        );
    }

    public static function sendCode(
        $type,
        $phone,
        $pattern = 'sfi4qv84ye9cffp'
    ): void {
        $code = self::generateRandomCode();

        VerificationCode::create([
            'phone' => $phone,
            'authenticatable_type' => $type,
            'code' => $code,
            'failed_attempts' => 0,
            'expired_at' => now()->addMinutes(
                (int) config('app.life_time')
            ),
        ]);

        if (App::environment('production')) {
            SmsJob::dispatch(
                $phone,
                $pattern,
                [
                    'verification-code' => (string) $code,
                ]
            )->onConnection('sync');
        }
    }

    public static function checkVerificationCodeIsValid(
        string $type,
        string $phone,
        string $code
    ): ?VerificationCode {

        Log::info('data', [
            'type' => $type,
            'phone' => $phone,
            'code' => $code,
        ]);

        return DB::transaction(function () use ($type, $phone, $code) {

            $verificationCode = VerificationCode::query()
                ->whereNull('verified_at')
                ->where('phone', $phone)
                ->where('authenticatable_type', $type)
                ->where('expired_at', '>=', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (!$verificationCode) {
                return null;
            }

            $isValid = (string) $verificationCode->code == $code;

            if ($isValid) {
                return $verificationCode;
            }

            $failedAttempts = $verificationCode->failed_attempts + 1;

            $updates = [
                'failed_attempts' => $failedAttempts,
            ];

            if ($failedAttempts >= self::MAX_FAILED_ATTEMPTS) {
                $updates['expired_at'] = now();
            }

            $verificationCode->update($updates);

            return null;
        });
    }

    private static function generateRandomCode(): int
    {
        if (App::environment('production')) {
            return random_int(100000, 999999);
        }

        return 123456;
    }
}
