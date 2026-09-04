<?php

namespace App\Services\Hamtala;

use App\Exceptions\HamtalaApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

readonly class HamtalaClient
{
    public function __construct(
        private string $baseUrl,
        private string $appKey,
    )
    {
    }

    /**
     * @throws HamtalaApiException
     * @throws ConnectionException
     */
    public function post(string $uri, array $body = []): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout(15)
            ->retry(2, 500, throw: false)
            ->acceptJson()
            ->asJson()
            ->post($uri, array_merge(['app_key' => $this->appKey], $body));

        if ($response->status() === 403) {
            $msg = $response->json('message')
                ?? $response->json('error')
                ?? 'Invalid app key';

//            Log::warning('Hamtala returned 403', [
//                'uri' => $uri,
//                'message' => $msg,
//                'status' => $response->status(),
//                'body' => $response->body(),
//            ]);

            throw new HamtalaApiException(
                message: $msg,
                statusCode: 403,
                responseBody: $response->json() ?? $response->body(),
            );
        }

        if ($response->failed()) {
//            Log::error('Hamtala API request failed', [
//                'uri' => $uri,
//                'status' => $response->status(),
//                'body' => $response->body(),
//            ]);

            throw new HamtalaApiException(
                message: "Hamtala request to [{$uri}] failed with status {$response->status()}",
                statusCode: $response->status(),
                responseBody: $response->json() ?? $response->body(),
            );
        }

        return $response->json() ?? [];
    }
}
