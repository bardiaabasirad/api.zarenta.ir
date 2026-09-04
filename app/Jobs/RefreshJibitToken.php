<?php

namespace App\Jobs;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshJibitToken
{
    public function handle(): void
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])
            ->post('https://napi.jibit.ir/ide/v1/tokens/generate', [
                'apiKey' => config('app.jibit_api_key'),
                'secretKey' => config('app.jibit_secret_key'),
            ]);

        if (!$response->successful()) {
            Log::error('Jibit API returned unsuccessful response: ' . $response->body());
            throw new \Exception('Failed to get Jibit token: ' . $response->status());
        }

        $responseData = $response->json();

        Setting::where('option_key', 'access_token')->update([
            'option_value' => $responseData['accessToken'],
        ]);

        Setting::where('option_key', 'refresh_token')->update([
            'option_value' => $responseData['refreshToken'],
        ]);

        Log::info('Jibit token refreshed successfully');
    }
}
