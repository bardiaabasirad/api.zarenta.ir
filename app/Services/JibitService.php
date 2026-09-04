<?php

namespace App\Services;

use App\Jobs\RefreshJibitToken;
use App\Models\Inquiry;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use function PHPUnit\Framework\isArray;

class JibitService
{
    /**
     * متد مرکزی برای ارسال درخواست‌های API به Jibit
     * حداکثر دو بار تلاش برای رفرش توکن در صورت forbidden
     */
    private static function callJibitApi(string $endpoint, array $payload = [], int $retry = 0)
    {
        $access_token = Setting::where('option_key', 'access_token')->first();

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token->option_value,
        ])->get("https://napi.jibit.ir/ide/v1/" . $endpoint, $payload);

        $data = $response->json();

        // در صورت خطای forbidden → حداکثر دو بار رفرش توکن و تلاش مجدد
        if (isset($data['code']) && $data['code'] === 'forbidden' && $retry < 2) {
            Log::warning('Forbidden detected — attempt #' . ($retry + 1) . ' to refresh Jibit token.');
            dispatch_sync(new RefreshJibitToken());
            return self::callJibitApi($endpoint, $payload, $retry + 1);
        }

        return [
            'raw' => $response,
            'data' => $data,
        ];
    }

    private static function checkSpecialStatuses(array $responseData): void
    {
        if (isset($responseData['code']) && $responseData['code'] === 'balance.not_enough') {
            Log::alert('The Jibit account balance is insufficient for the inquiry.');
        }

        if (isset($responseData['code']) && $responseData['code'] === 'forbidden') {
            Log::alert('Forbidden access detected.');
            dispatch_sync(new RefreshJibitToken());
        }
    }

    public static function matching($request)
    {
        $payload = [];

        if (isset($request->iban)) {
            $inquiry = Inquiry::where('queried_at', '>=', now()->subHours(24))
                ->where('fields->iban', $request->iban)
                ->where('fields->national_code', $request->national_code)
                ->where('fields->birthdate', $request->birthdate)
                ->first();

            if ($inquiry) {
                return [
                    'code' => $inquiry->response['code'],
                    'inquiry' => $inquiry,
                ];
            }

            $payload = [
                'iban' => $request->iban,
                'nationalCode' => $request->national_code,
                'birthDate' => $request->birthdate,
            ];
        } elseif (isset($request->card_number)) {
            $inquiry = Inquiry::where('queried_at', '>=', now()->subHours(24))
                ->where('fields->card_number', $request->card_number)
                ->where('fields->birthdate', $request->birthdate)
                ->where('fields->national_code', $request->national_code)
                ->first();

            if ($inquiry) {
                return [
                    'code' => $inquiry->response['code'],
                    'inquiry' => $inquiry,
                ];
            }

            $payload = [
                'cardNumber' => $request->card_number,
                'nationalCode' => $request->national_code,
                'birthDate' => $request->birthdate,
            ];
        } elseif (isset($request->mobile_number)) {
            $inquiry = Inquiry::where('queried_at', '>=', now()->subHours(24))
                ->where('fields->mobile_number', $request->mobile_number)
                ->where('fields->national_code', $request->national_code)
                ->first();

            if ($inquiry) {
                return [
                    'code' => $inquiry->response['code'],
                    'inquiry' => $inquiry,
                ];
            }

            $payload = [
                'nationalCode' => $request->national_code,
                'mobileNumber' => $request->mobile_number,
            ];
        }

        try {
            $responsePack = self::callJibitApi("services/matching", $payload);

            $response = $responsePack['raw'];
            $responseData = $responsePack['data'];

            if ($response->successful()) {
                $matched = $responseData['matched'] ?? false;

                $code = $matched ? 'valid' : 'not_valid';

                $fields = [
                    'national_code' => $request->national_code,
                    'birthdate' => $request->birthdate ?? null,
                ];

                if (isset($request->iban)) {
                    $fields['iban'] = $request->iban;
                } elseif (isset($request->card_number)) {
                    $fields['card_number'] = $request->card_number;
                } elseif (isset($request->mobile_number)) {
                    $fields['mobile_number'] = $request->mobile_number;
                }

                $inquiry = Inquiry::create([
                    'fields' => $fields,
                    'response' => [
                        'status' => 'success',
                        'code' => $code,
                    ],
                    'queried_at' => Carbon::now(),
                ]);

                return [
                    'code' => $code,
                    'inquiry' => $inquiry,
                ];
            }

            if (isArray($responseData)) {
                self::checkSpecialStatuses($responseData);
            } else {
                throw new \Exception('Response data is not an array');
            }

            return [
                'code' => $responseData['code'] ?? 'unknown_error',
            ];
        } catch (\Exception $exception) {
            return [
                'code' => 'curl_error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    public static function similarity($request)
    {
        try {
            $inquiry = Inquiry::where('queried_at', '>=', now()->subHours(24))
                ->where('fields->nationalCode', $request->national_code)
                ->where('fields->birthDate', $request->birthdate)
                ->where('fields->firstName', $request->firstname)
                ->where('fields->lastName', $request->lastname)
                ->where('fields->fullName', $request->firstname . ' ' . $request->lastname)
                ->where('fields->fatherName', $request->father_name)
                ->first();

            if ($inquiry) {
                return [
                    'code' => $inquiry->response['code'],
                    'inquiry' => $inquiry,
                    'firstNameSimilarityPercentage' => $inquiry->response['firstNameSimilarityPercentage'],
                    'lastNameSimilarityPercentage' => $inquiry->response['lastNameSimilarityPercentage'],
                    'fullNameSimilarityPercentage' => $inquiry->response['fullNameSimilarityPercentage'],
                    'fatherNameSimilarityPercentage' => $inquiry->response['fatherNameSimilarityPercentage'],
                ];
            }

            $payload = [
                'nationalCode' => $request->national_code,
                'birthDate' => $request->birthdate,
                'firstName' => $request->firstname,
                'lastName' => $request->lastname,
                'fullName' => $request->firstname . ' ' . $request->lastname,
                'fatherName' => $request->father_name,
            ];

            $responsePack = self::callJibitApi("services/identity/similarity", $payload);
            $response = $responsePack['raw'];
            $responseData = $responsePack['data'];

            if ($response->successful()) {
                $inquiry = Inquiry::create([
                    'fields' => $payload,
                    'response' => [
                        'status' => 'success',
                        'code' => 'valid',
                        'firstNameSimilarityPercentage' => $responseData['firstNameSimilarityPercentage'] ?? 0,
                        'lastNameSimilarityPercentage' => $responseData['lastNameSimilarityPercentage'] ?? 0,
                        'fullNameSimilarityPercentage' => $responseData['fullNameSimilarityPercentage'] ?? 0,
                        'fatherNameSimilarityPercentage' => $responseData['fatherNameSimilarityPercentage'] ?? 0,
                    ],
                    'queried_at' => Carbon::now(),
                ]);

                return [
                    'code' => 'valid',
                    'inquiry' => $inquiry,
                    'firstNameSimilarityPercentage' => $responseData['firstNameSimilarityPercentage'] ?? 0,
                    'lastNameSimilarityPercentage' => $responseData['lastNameSimilarityPercentage'] ?? 0,
                    'fullNameSimilarityPercentage' => $responseData['fullNameSimilarityPercentage'] ?? 0,
                    'fatherNameSimilarityPercentage' => $responseData['fatherNameSimilarityPercentage'] ?? 0,
                ];
            }

            self::checkSpecialStatuses($responseData);

            return [
                'code' => $responseData['code'] ?? 'unknown_error',
            ];
        } catch (\Exception $exception) {
            return [
                'code' => 'curl_error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    public static function iban($request)
    {
        try {
            $inquiry = Inquiry::where('queried_at', '>=', now()->subHours(24))
                ->where('fields->iban', $request->iban)
                ->first();

            if ($inquiry) {
                return [
                    'code' => $inquiry->response['code'],
                    'inquiry' => $inquiry,
                ];
            }

            $payload = ['value' => $request->iban];
            $responsePack = self::callJibitApi("ibans", $payload);
            $response = $responsePack['raw'];
            $responseData = $responsePack['data'];

            if ($response->successful()) {
                $inquiry = Inquiry::create([
                    'fields' => ['iban' => $request->iban],
                    'response' => [
                        'status' => 'success',
                        'code' => 'valid',
                        'ibanInfo' => $responseData['ibanInfo'] ?? null,
                    ],
                    'queried_at' => Carbon::now(),
                ]);

                return [
                    'code' => 'valid',
                    'inquiry' => $inquiry,
                ];
            }

            self::checkSpecialStatuses($responseData);

            return ['code' => $responseData['code'] ?? 'unknown_error'];
        } catch (\Exception $exception) {
            return [
                'code' => 'curl_error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    public static function cards($request)
    {
        try {
            $inquiry = Inquiry::where('queried_at', '>=', now()->subHours(24))
                ->where('fields->card', $request->iban)
                ->first();

            if ($inquiry) {
                return [
                    'code' => $inquiry->response['code'],
                    'inquiry' => $inquiry,
                ];
            }

            $payload = ['number' => $request->iban];
            $responsePack = self::callJibitApi("cards", $payload);
            $response = $responsePack['raw'];
            $responseData = $responsePack['data'];

            if ($response->successful()) {
                $inquiry = Inquiry::create([
                    'fields' => ['card' => $request->iban],
                    'response' => [
                        'status' => 'success',
                        'code' => 'valid',
                        'cardInfo' => $responseData['cardInfo'] ?? null,
                    ],
                    'queried_at' => Carbon::now(),
                ]);

                return [
                    'code' => 'valid',
                    'inquiry' => $inquiry,
                ];
            }

            self::checkSpecialStatuses($responseData);
            return ['code' => $responseData['code'] ?? 'unknown_error'];
        } catch (\Exception $exception) {
            return [
                'code' => 'curl_error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    public static function ibanFromCard($request)
    {
        try {
            $inquiry = Inquiry::where('queried_at', '>=', now()->subHours(24))
                ->where('fields->card', $request->card)
                ->where('fields->get_iban', 'yes')
                ->first();

            if ($inquiry) {
                return [
                    'code' => $inquiry->response['code'],
                    'inquiry' => $inquiry,
                ];
            }

            $payload = [
                'number' => $request->card,
                'iban' => 'true',
            ];

            $responsePack = self::callJibitApi("cards", $payload);
            $response = $responsePack['raw'];
            $responseData = $responsePack['data'];

            if ($response->successful()) {
                $inquiry = Inquiry::create([
                    'fields' => [
                        'card' => $request->card,
                        'get_iban' => 'yes',
                    ],
                    'response' => [
                        'status' => 'success',
                        'code' => 'valid',
                        'ibanInfo' => $responseData['ibanInfo'] ?? null,
                    ],
                    'queried_at' => Carbon::now(),
                ]);

                return [
                    'code' => 'valid',
                    'inquiry' => $inquiry,
                ];
            }

            self::checkSpecialStatuses($responseData);
            return ['code' => $responseData['code'] ?? 'unknown_error'];
        } catch (\Exception $exception) {
            return [
                'code' => 'curl_error',
                'message' => $exception->getMessage(),
            ];
        }
    }
}
