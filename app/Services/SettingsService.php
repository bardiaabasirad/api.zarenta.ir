<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class SettingsService
{
    /**
     * Get a single setting value
     */
    public function get(string $key, $default = null)
    {
        return Cache::remember('setting.' . $key, 3600, function () use ($key, $default) {
            $setting = Setting::where('option_key', $key)->first();
            return $setting ? $setting->option_value : $default;
        });
    }

    /**
     * Get multiple settings at once
     */
    public function getMany(array $keys, array $defaults = []): Collection
    {
        // Try to get from cache first
        $cachedSettings = collect($keys)->mapWithKeys(function ($key) use ($defaults) {
            $cached = Cache::get('setting.' . $key);
            if ($cached !== null) {
                return [$key => $cached];
            }
            return [];
        });

        // Find which keys we need to query from database
        $missingKeys = collect($keys)->diff($cachedSettings->keys());

        if ($missingKeys->isNotEmpty()) {
            // Query missing settings from database
            $dbSettings = Setting::whereIn('option_key', $missingKeys)
                ->get()
                ->mapWithKeys(function ($setting) {
                    // Cache the result
                    Cache::put('setting.' . $setting->option_key, $setting->option_value, 3600);
                    return [$setting->option_key => $setting->option_value];
                });

            // Merge cached and database results
            $settings = $cachedSettings->merge($dbSettings);
        } else {
            $settings = $cachedSettings;
        }

        // Add defaults for any missing keys
        return collect($keys)->mapWithKeys(function ($key) use ($settings, $defaults) {
            return [$key => $settings->get($key, $defaults[$key] ?? null)];
        });
    }

    /**
     * Set a single setting value
     */
    public function set(string $key, $value)
    {
        $setting = Setting::updateOrCreate(
            ['option_key' => $key],
            ['option_value' => $value]
        );

        Cache::forget('setting.' . $key);

        return $setting;
    }

    /**
     * Set multiple settings at once
     */
    public function setMany(array $settings): Collection
    {
        return collect($settings)->map(function ($value, $key) {
            return $this->set($key, $value);
        });
    }
}
