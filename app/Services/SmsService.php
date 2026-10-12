<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SmsService
{
    /**
     * ارسال پیامک اعتبارسنجی / پترن (Static)
     */
    public static function sendVerify(string $mobile, int $templateId, array $parameters): array
    {
        $apiKey = config('services.smsir.api_key');
        $baseUrl = config('services.smsir.base_url', 'https://api.sms.ir/v1');

        $normalizedMobile = self::normalizeMobile($mobile);
        $formattedParameters = self::formatParameters($parameters);

        $payload = [
            'mobile' => $normalizedMobile,
            'templateId' => $templateId,
            'parameters' => $formattedParameters,
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key'    => $apiKey,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->retry(2, 200)
                ->post("{$baseUrl}/send/verify", $payload);

            if ($response->failed()) {
                Log::error('SMS.ir Verification Failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                throw new Exception("خطا در پاسخ سرویس پیامک: " . $response->status());
            }

            return $response->json();

        } catch (Exception $e) {
            Log::critical('SMS.ir Static Service Error: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function formatParameters(array $parameters): array
    {
        // اگر از قبل ساختار name و value داشت، دست نزن
        if (!empty($parameters) && isset($parameters[0]['name'], $parameters[0]['value'])) {
            return $parameters;
        }

        $formatted = [];
        foreach ($parameters as $name => $value) {
            $formatted[] = [
                'name'  => (string) $name,
                'value' => (string) $value,
            ];
        }

        return $formatted;
    }

    private static function normalizeMobile(string $mobile): string
    {
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabicDigits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $mobile = str_replace(array_merge($persianDigits, $arabicDigits), $englishDigits, $mobile);
        $mobile = preg_replace('/[^0-9]/', '', $mobile);

        if (str_starts_with($mobile, '989')) {
            $mobile = '0' . substr($mobile, 2);
        }

        return $mobile;
    }
}
