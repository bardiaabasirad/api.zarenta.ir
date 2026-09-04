<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SmsService
{
    public static function sendPattern($fromNumber, $patternCode, $recipient, $params, $phonebook = null)
    {
        $baseUrl = config('services.sms.base_url');
        $token = config('services.sms.token');

        $payload = [
            'sending_type' => 'pattern',
            'from_number' => $fromNumber,
            'code' => $patternCode,
            'recipients' => [$recipient],
            'params' => $params,
        ];

        if ($phonebook) {
            $payload['phonebook'] = $phonebook;
        }

        return Http::withHeaders([
            'Authorization' => $token,
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/api/send", $payload);
    }
}
