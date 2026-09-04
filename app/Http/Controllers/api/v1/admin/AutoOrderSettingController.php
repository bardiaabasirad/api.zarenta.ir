<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Events\SettingsChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAutoOrderSettingRequest;
use App\Http\Requests\UpdateAutoOrderSettingRequest;
use App\Models\MetalItemAutoOrderSetting;

class AutoOrderSettingController extends Controller
{

    public function store(StoreAutoOrderSettingRequest $request)
    {
        $setting = MetalItemAutoOrderSetting::create($request->validated());

        SettingsChanged::dispatch();

        return $setting->load(['metalItem:id,title', 'priceSource:id,name']);
    }

    public function update(UpdateAutoOrderSettingRequest $request, MetalItemAutoOrderSetting $auto_order_setting)
    {
        $auto_order_setting->update($request->validated());

        SettingsChanged::dispatch();

        return $auto_order_setting->load(['metalItem:id,title', 'priceSource:id,name']);
    }

    public function destroy(MetalItemAutoOrderSetting $auto_order_setting)
    {
        $auto_order_setting->delete();

        SettingsChanged::dispatch();

        return response()->noContent();
    }
}
