<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    description: "API Documentation for Zhikgold Project",
    title: "Zhikgold Project API",
    contact: new OA\Contact(
        name: "Contact Technical Support",
        url: "tel:09124848784",
    ),
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: "Main API Server"
)]
#[OA\SecurityScheme(
    securityScheme: "X-API-Key",
    type: "apiKey",
    description: "API Key for authentication",
    name: "X-API-Key",
    in: "header"
)]
#[OA\Schema(
    schema: "Error401",
    title: "Unauthorized Error",
    properties: [
        new OA\Property(property: "error", type: "string", example: "invalid_api_key")
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "Error403",
    title: "Subscription Expired Error",
    properties: [
        new OA\Property(property: "message", type: "string", example: "Subscription expired."),
        new OA\Property(property: "error_code", type: "string", example: "SUBSCRIPTION_EXPIRED")
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "ProductPriceItem",
    title: "Product Price Item",
    properties: [
        new OA\Property(property: "product_id", type: "integer", example: 10),
        new OA\Property(property: "product_name", type: "string", example: "آبشده نقد حاضر"),
        new OA\Property(property: "buy", type: "integer", example: 69247000),
        new OA\Property(property: "sell", type: "integer", example: 68543000),
        new OA\Property(property: "buy_status", type: "string", example: "inactive", enum: ["active", "inactive"]),
        new OA\Property(property: "sell_status", type: "string", example: "inactive", enum: ["active", "inactive"]),
        new OA\Property(property: "time", type: "string", example: "2026-06-22 12:41:29")
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "ProductPriceListResponse",
    title: "Product Price List Response",
    type: "array",
    items: new OA\Items(ref: "#/components/schemas/ProductPriceItem")
)]
#[OA\Schema(
    schema: "MarketPrice",
    required: ["id", "ounce", "price", "time"],
    properties: [
        new OA\Property(property: "id", type: "integer", example: 398218),
        new OA\Property(property: "ounce", type: "string", example: "4082"),
        new OA\Property(property: "price", type: "string", example: "16298000"),
        new OA\Property(property: "imami_coin", type: "string", example: "164050"),
        new OA\Property(property: "bahar_coin", description: "باید با bahar_azadi_coin در کنترلر تطبیق داده شود", type: "string", example: "158600"),
        new OA\Property(property: "half_coin", type: "string", example: "85700"),
        new OA\Property(property: "quarter_coin", type: "string", example: "50000"),
        new OA\Property(property: "dollar", type: "string", example: "161500"),
        new OA\Property(property: "euro", type: "string", example: "184250"),
        new OA\Property(property: "derham", description: "باید با dirham در کنترلر تطبیق داده شود", type: "string", example: "44670"),
        new OA\Property(property: "time", type: "string", format: "datetime", example: "2026-06-24 11:03:21"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "OrderRequest",
    required: ["quantity", "order_type", "mazane", "product_id", "order_id"],
    properties: [
        new OA\Property(property: "quantity", type: "integer", example: 10),
        new OA\Property(property: "order_type", type: "string", example: "buy", enum: ["buy", "sell"]),
        new OA\Property(property: "mazane", type: "integer", example: 69650000, nullable: true),
        new OA\Property(property: "product_id", type: "integer", example: 1),
        new OA\Property(property: "order_id", type: "string", example: "58R4rG6", nullable: true),
    ]
)]
#[OA\Schema(
    schema: "OrderResponse",
    properties: [
        new OA\Property(property: "tracking_code", type: "integer", example: 4011508989),
        new OA\Property(property: "product_name", type: "string", example: "آبشده نقد فردا"),
        new OA\Property(property: "order_type", type: "string", example: "buy", enum: ["buy", "sell"]),
        new OA\Property(property: "status", type: "string", example: "pending", enum: ["pending", "processing", "succeed", "rejected"]),
        new OA\Property(property: "quantity", type: "string", example: "10"),
        new OA\Property(property: "fee", type: "integer", example: 70470000),
        new OA\Property(property: "message", type: "string", example: "سفارش شما با موفقیت ثبت شد و در صف بررسی قرار دارد"),
        new OA\Property(property: "created_at", type: "string", example: "1405/04/03 12:54:53"),
    ]
)]
#[OA\Schema(
    schema: "OrderValidationError",
    properties: [
        new OA\Property(property: "message", type: "string", example: "مظنه ارسالی با آخرین مظنه بازار مطابقت ندارد."),
        new OA\Property(
            property: "errors",
            properties: [
                new OA\Property(
                    property: "mazane",
                    type: "array",
                    items: new OA\Items(type: "string"),
                    example: ["مظنه فعلی: 70,470,000 تومان"]
                )
            ],
            type: "object"
        ),
        new OA\Property(property: "current_mazane", type: "integer", example: 70470000),
        new OA\Property(property: "submitted_mazane", type: "string", example: "70470004")
    ]
)]
#[OA\Schema(
    schema: "OrderStatusItem",
    required: ["tracking_code", "product_name", "order_type", "status", "quantity", "fee", "message", "created_at"],
    properties: [
        new OA\Property(property: "tracking_code", type: "string", example: "7987950561"),
        new OA\Property(property: "product_name", type: "string", example: "آبشده نقد فردا"),
        new OA\Property(property: "order_type", type: "string", example: "buy", enum: ["buy", "sell"]),
        new OA\Property(property: "status", type: "string", example: "pending", enum: ["pending", "completed", "cancelled"]),
        new OA\Property(property: "quantity", type: "string", example: "10"),
        new OA\Property(property: "fee", type: "integer", example: 70470000),
        new OA\Property(property: "message", type: "string", example: "سفارش شما با موفقیت ثبت شد و در صف بررسی قرار دارد"),
        new OA\Property(property: "created_at", type: "string", example: "1405/04/03 13:11:03")
    ]
)]
#[OA\Schema(
    schema: 'MetalPriceWebhookPayload',
    title: 'Metal Price Webhook Payload',
    description: 'Detailed structure of the data sent to the trader webhook URL',
    required: ['event', 'event_id', 'product_id', 'product', 'buy', 'sell', 'updated_at'],
    properties: [
        new OA\Property(
            property: 'event',
            description: 'Webhook event name',
            type: 'string',
            example: 'metal_price.updated'
        ),
        new OA\Property(
            property: 'event_id',
            description: 'Unique event identifier',
            type: 'string',
            example: '01HQXYZ123456789ABCDEF0000'
        ),
        new OA\Property(
            property: 'product_id',
            description: 'Product identifier (metal_item)',
            type: 'integer',
            example: 14,
            nullable: false
        ),
        new OA\Property(
            property: 'product',
            description: 'Product title',
            type: 'string',
            example: 'آبشده نقد فردا',
            nullable: false
        ),
        new OA\Property(
            property: 'buy',
            description: 'Buy price',
            example: 69247000
        ),
        new OA\Property(
            property: 'sell',
            description: 'Sell price',
            example: 68543000
        ),
        new OA\Property(
            property: 'min_order',
            description: 'Minimum order quantity',
            example: 1
        ),
        new OA\Property(
            property: 'max_order',
            description: 'Maximum order quantity',
            example: 100
        ),
        new OA\Property(
            property: 'updated_at',
            description: 'Update timestamp in ISO 8601 format',
            type: 'string',
            format: 'date-time',
            example: '2026-08-28T14:30:21+00:00'
        ),
    ],
    type: 'object'
)]
#[OA\Post(
    path: '/trader-endpoint-sample/webhook-receiver',
    description: <<<'MARKDOWN'
This is a mock / documentation endpoint demonstrating how you (the Trader) should configure, receive, and verify incoming webhook requests dispatched by Zhikgold.

---

### Delivery Conditions & Network Behavior
- **Trigger:** Webhooks are triggered automatically immediately after new metal prices are committed to the database.
- **Requirements:**
  1. Your trader account status must be active (`is_active = true`).
  2. Webhook integration must be enabled (`webhook_enabled = true`).
  3. A valid HTTPS endpoint (`webhook_url`) and `webhook_secret` must be configured.
- **Timeouts:**
  - Connection timeout: **1 second**
  - Total request timeout: **3 seconds**
- **Retry Mechanism:**
  - In case of network failure or HTTP `5xx`/`4xx` responses, the system retries **2 times** (total 3 attempts).
  - Backoff delay between attempts: **2 seconds**.

---

### Webhook Security & Verification Guide
To protect your endpoints against **Spoofing**, **Data Tampering**, and **Replay Attacks**, Zhikgold signs every request cryptographically using HMAC-SHA256 with your private `webhook_secret`.

```text
1. Signature Construction
    payload_to_sign = "{timestamp}.{event_id}.{raw_request_body}"
    computed_hash   = hash_hmac("sha256", payload_to_sign, webhook_secret)
    expected_header = "v1=" + computed_hash
2. Verification Steps
    Check Required Headers: Extract X-Webhook-Timestamp, X-Webhook-Id, and X-Webhook-Signature.
    Prevent Replay Attacks: Ensure abs(current_timestamp - X-Webhook-Timestamp) <= 300 (reject requests older than 5 minutes).
    Verify Signature: Compute the HMAC hash using the raw unparsed HTTP body ($request->getContent()) and compare it using timing-safe comparison (hash_equals).
    Enforce Idempotency: Track X-Webhook-Id (e.g., in Redis / Cache) to skip already-processed duplicate dispatches.
    Return HTTP 200: Respond with HTTP status 200 OK promptly to prevent timeouts.
    Implementation Example (PHP / Laravel)

<?php
public function handleWebhook(\Illuminate\Http\Request $request)
{
    $secret    = config('services.zhikgold.webhook_secret');
    $timestamp = $request->header('X-Webhook-Timestamp');
    $eventId   = $request->header('X-Webhook-Id');
    $signature = $request->header('X-Webhook-Signature');

    // 1. Validate security headers presence
    if (! $timestamp || ! $eventId || ! $signature) {
        return response()->json(['message' => 'Missing signature headers.'], 401);
    }

    // 2. Reject replay attacks (tolerance: 5 minutes)
    if (abs(time() - (int) $timestamp) > 300) {
        return response()->json(['message' => 'Expired webhook timestamp.'], 401);
    }

    // 3. Compute expected signature using RAW body
    $rawBody = $request->getContent();

    $expectedSignature = str_replace('v1=', '', $signature);

    $computedSignature = hash_hmac(
        'sha256',
        "{$timestamp}.{$eventId}.{$rawBody}",
        $secret
    );

    if (! hash_equals($computedSignature, $expectedSignature)) {
        return response()->json(['message' => 'Invalid webhook signature.'], 401);
    }

    // 4. Enforce idempotency (deduplication)
    $cacheKey = "webhook_event:{$eventId}";
    if (! \Illuminate\Support\Facades\Cache::add($cacheKey, true, now()->addDay())) {
        return response()->json(['message' => 'Duplicate event ignored.'], 200);
    }

    // 5. Process metal price payload
    $payload = $request->json()->all();
    // Your business logic here ...

    return response()->json(['status' => 'success'], 200);
}
MARKDOWN,
    summary: 'Sample Trader Webhook Receiver Endpoint',

    requestBody: new OA\RequestBody(
        description: 'Real-time updated metal price payload dispatched to trader',
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/MetalPriceWebhookPayload')
    ),
    tags: ['Webhooks Integration'],
    parameters: [
        new OA\Parameter(
            name: 'X-Webhook-Signature',
            description: 'HMAC-SHA256 digital signature formatted as v1={hash}',
            in: 'header',
            required: true,
            schema: new OA\Schema(type: 'string', example: 'v1=8f7e8a9b0c7d6e5f4a3b2c1d0e9f8a7b6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f')
        ),
        new OA\Parameter(
            name: 'X-Webhook-Timestamp',
            description: 'UNIX timestamp (in seconds) when the webhook dispatch was initiated. Used for replay attack prevention.',
            in: 'header',
            required: true,
            schema: new OA\Schema(type: 'integer', example: 1787841000)
        ),
        new OA\Parameter(
            name: 'X-Webhook-Id',
            description: 'Unique event identifier (ULID/UUID) for idempotency and duplicate delivery handling.',
            in: 'header',
            required: true,
            schema: new OA\Schema(type: 'string', example: '01HQXYZ123456789ABCDEF0000')
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Webhook successfully received, signature verified, and acknowledged by trader',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success')
                ]
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthorized: Missing headers, invalid HMAC-SHA256 signature, or expired timestamp (replay attempt)',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Invalid signature.')
                ]
            )
        ),
        new OA\Response(
            response: 500,
            description: 'Internal Server Error on trader side: System will retry delivery based on retry policy.'
        )
    ]
)]

abstract class OpenApi

{

}
