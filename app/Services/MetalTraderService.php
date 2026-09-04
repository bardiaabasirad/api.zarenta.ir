<?php

namespace App\Services;

use App\Jobs\SmsJob;
use App\Models\MetalTrader;
use App\Models\MetalTraderLog;
use App\Models\MetalTraderSuspension;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MetalTraderService
{
    public function generateApiKey(): string
    {
        do {
            $str = \Illuminate\Support\Str::random(32);
        } while(MetalTrader::where('api_key', $str)->exists());

        return $str;
    }

    /**
     * به‌روزرسانی MetalTrader همراه با ثبت لاگ تغییرات و مدیریت وضعیت.
     */
    public function updateWithLogging(MetalTrader $trader, array $attributes): ?string
    {
        $original = $trader->getOriginal();

        $trader->fill($attributes);
        $dirty = $trader->getDirty();

        $oneTimeWebhookSecret = null;

        $webhookUrlWasChanged = array_key_exists('webhook_url', $dirty);

        if ($webhookUrlWasChanged) {
            if (filled($trader->webhook_url)) {
                $oneTimeWebhookSecret = bin2hex(random_bytes(32));

                $trader->webhook_secret = $oneTimeWebhookSecret;
                $trader->webhook_secret_version = 'v1';

                // تا قبل از ارسال تست و تأیید، فعال نشود.
                $trader->webhook_enabled = false;
            } else {
                // URL پاک شده است؛ ارسال webhook را کاملاً غیرفعال کن.
                $trader->webhook_secret = null;
                $trader->webhook_enabled = false;
            }
        }

        $dirty = $trader->getDirty();

        $trader->save();

        // webhook_secret نباید در audit log ذخیره شود.
        $safeDirty = Arr::except($dirty, [
            'webhook_secret',
        ]);

        $oldValues = Arr::except(
            array_intersect_key($original, $safeDirty),
            ['webhook_secret']
        );

        $newValues = Arr::except(
            $trader->getChanges(),
            [
                'updated_at',
                'webhook_secret',
            ]
        );

        $this->handleStatusChange($trader, $attributes);

        if (! empty($safeDirty)) {
            $this->logChanges($trader, $oldValues, $newValues);
        }

        return $oneTimeWebhookSecret;
    }
    /**
     * مدیریت تغییر وضعیت (فعال/غیرفعال).
     */
    private function handleStatusChange(MetalTrader $trader, array $attributes): void
    {
        if (! isset($attributes['status'])) {
            return;
        }

        match ($attributes['status']) {
            'active'   => $this->activate($trader),
            'inactive' => $this->deactivate($trader, $attributes),
            default    => null,
        };
    }

    /**
     * رفع تعلیق آخرین رکورد تعلیق.
     */
    private function activate(MetalTrader $trader): void
    {
        $suspension = MetalTraderSuspension::where('metal_trader_id', $trader->id)
            ->latest()
            ->first();

        if ($suspension) {
            $suspension->update(['unsuspended_at' => now()]);
        }
    }

    /**
     * ثبت تعلیق و باطل‌کردن توکن‌ها.
     */
    private function deactivate(MetalTrader $trader, array $attributes): void
    {
        MetalTraderSuspension::create([
            'metal_trader_id' => $trader->id,
            'suspended_by'    => Auth::id(),
            'suspended_at'    => now(),
            'block_reason_id' => $attributes['block_reason_id'] ?? null,
            'description'     => $attributes['description'] ?? null,
        ]);

        $this->revokeTokens($trader);
    }

    /**
     * حذف تمام توکن‌های Sanctum کاربر.
     */
    private function revokeTokens(MetalTrader $trader): void
    {
        if ($trader->tokens()->exists()) {
            $trader->tokens()->delete();
        } else {
            Log::info('No tokens found for MetalTrader ID: ' . $trader->id);
        }
    }

    /**
     * ثبت لاگ تغییرات.
     */
    private function logChanges(MetalTrader $trader, array $oldValues, array $newValues): void
    {
        $user = Auth::user();

        MetalTraderLog::create([
            'metal_trader_id' => $trader->id,
            'loggable_id'     => $user?->getKey(),
            'loggable_type'   => $user ? $user::class : null,
            'new_values'      => $newValues,
            'old_values'      => $oldValues,
        ]);
    }

    public function notify(MetalTrader $metalTrader)
    {
        SmsJob::dispatch(
            $metalTrader->phone,
            'e63nlkq9xrs826k',
            [
                'name' => "$metalTrader->name"
            ]
        );
    }
}
