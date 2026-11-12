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
    /**
     * به‌روزرسانی MetalTrader همراه با ثبت لاگ تغییرات و مدیریت وضعیت.
     */
    public function updateWithLogging(MetalTrader $trader, array $attributes): void
    {
        $original = $trader->getOriginal();

        $trader->fill($attributes);
        $dirty = $trader->getDirty();

        $trader->save();

        $oldValues = array_intersect_key($original, $dirty);
        $newValues = Arr::except($trader->getChanges(), ['updated_at']);

        $this->handleStatusChange($trader, $attributes);

        if (! empty($dirty)) {
            $this->logChanges($trader, $oldValues, $newValues);
        }
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
            'active' => $this->activate($trader),
            'inactive' => $this->deactivate($trader, $attributes),
            default => null,
        };
    }

    private function activate(MetalTrader $trader): void
    {
        $suspension = MetalTraderSuspension::where('metal_trader_id', $trader->id)
            ->latest()
            ->first();

        if ($suspension) {
            $suspension->update(['unsuspended_at' => now()]);
        }
    }

    private function deactivate(MetalTrader $trader, array $attributes): void
    {
        MetalTraderSuspension::create([
            'metal_trader_id' => $trader->id,
            'suspended_by' => Auth::id(),
            'suspended_at' => now(),
            'block_reason_id' => $attributes['block_reason_id'] ?? null,
            'description' => $attributes['description'] ?? null,
        ]);

        $this->revokeTokens($trader);
    }

    private function revokeTokens(MetalTrader $trader): void
    {
        if ($trader->tokens()->exists()) {
            $trader->tokens()->delete();
        } else {
            Log::info('No tokens found for MetalTrader ID: '.$trader->id);
        }
    }

    private function logChanges(MetalTrader $trader, array $oldValues, array $newValues): void
    {
        $user = Auth::user();

        MetalTraderLog::create([
            'metal_trader_id' => $trader->id,
            'loggable_id' => $user?->getKey(),
            'loggable_type' => $user ? $user::class : null,
            'new_values' => $newValues,
            'old_values' => $oldValues,
        ]);
    }

    public function notify(MetalTrader $metalTrader)
    {
        SmsJob::dispatch(
            $metalTrader->phone,
            'e63nlkq9xrs826k',
            [
                'name' => "$metalTrader->name",
            ]
        );
    }
}
