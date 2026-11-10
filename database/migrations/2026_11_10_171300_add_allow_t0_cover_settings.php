<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'allow_t0_cover_t1' => 'اجازه تخصیص T0 به T1',
            'allow_t0_cover_t2' => 'اجازه تخصیص T0 به T2',
        ] as $key => $name) {
            $setting = Setting::query()->firstOrNew(['option_key' => $key]);
            $setting->option_name = $name;
            $setting->option_value = $setting->exists ? ($setting->option_value ?? '0') : '0';
            $setting->save();
        }
    }

    public function down(): void
    {
        Setting::query()
            ->whereIn('option_key', ['allow_t0_cover_t1', 'allow_t0_cover_t2'])
            ->delete();
    }
};
