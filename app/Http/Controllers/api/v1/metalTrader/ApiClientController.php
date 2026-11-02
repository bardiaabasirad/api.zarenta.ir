<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Requests\MetalTraderAvatarUploadRequest;
use App\Http\Resources\ClientInfoResource;
use App\Models\DealingGroup;
use App\Models\MetalTrader;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ApiClientController extends Controller
{
    public function info()
    {
        $metalTrader = MetalTrader::select(
                'id','name','api_key','dealing_group_id','kimi_account_id',
                'phone','avatar','balance','market_opening_notification','aggregated_view_of_invoices'
            )
            ->with('dealingGroup.metalItemConfigs')
            ->withHasPass()
            ->find(Auth::guard('metal-trader-api')->id());

        $metalTrader->inquiry_access = $metalTrader->subscriptionNotExpired('inquiry');

        if (! $metalTrader->dealingGroup) {
            $setting = Setting::firstWhere('option_key', 'default_metal_trader_group_id');

            if ($setting) {
                $defaultDealingGroup = DealingGroup::with('metalItemConfigs')->find($setting->option_value);

                if ($defaultDealingGroup) {
                    $metalTrader->setRelation('dealingGroup', $defaultDealingGroup);
                }
            }
        }

        return response()->json(new ClientInfoResource($metalTrader));
    }

    public function uploadAvatar(MetalTraderAvatarUploadRequest $request): JsonResponse
    {
        /** @var MetalTrader $metalTrader */
        $metalTrader = Auth::guard('metal-trader-api')->user();

        $previousAvatar = $metalTrader->avatar;
        $path = Storage::put('metal-traders', $request->file('image'));

        $metalTrader->update(['avatar' => $path]);

        if ($previousAvatar && Storage::exists($previousAvatar)) {
            Storage::delete($previousAvatar);
        }

        return response()->json([
            'avatar' => $path,
            'message' => 'تصویر پروفایل با موفقیت به‌روزرسانی شد',
        ]);
    }

    public function deleteAvatar(): JsonResponse
    {
        /** @var MetalTrader $metalTrader */
        $metalTrader = Auth::guard('metal-trader-api')->user();

        $previousAvatar = $metalTrader->avatar;

        if ($previousAvatar) {
            $metalTrader->update(['avatar' => null]);

            if (Storage::exists($previousAvatar)) {
                Storage::delete($previousAvatar);
            }
        }

        return response()->json([
            'avatar' => null,
            'message' => 'تصویر پروفایل حذف شد',
        ]);
    }

    /**
     * Generate and store new API key for a client
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateApiKey(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $apiClient = MetalTrader::create([
            'name' => $request->name,
            'api_key' => MetalTrader::generateApiKey()
        ]);

        return response()->json([
            'message' => 'API key generated successfully',
            'api_key' => $apiClient->api_key
        ], 201);
    }

    /**
     * Regenerate API key for existing client
     *
     * @param MetalTrader $apiClient
     * @return JsonResponse
     */
    public function regenerateApiKey(MetalTrader $apiClient)
    {
        $apiClient->update([
            'api_key' => MetalTrader::generateApiKey()
        ]);

        return response()->json([
            'message' => 'API key regenerated successfully',
            'api_key' => $apiClient->api_key
        ], 200);
    }
}
