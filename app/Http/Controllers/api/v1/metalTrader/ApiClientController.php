<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Resources\ClientInfoResource;
use App\Models\DealingGroup;
use App\Models\MetalTrader;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ApiClientController extends Controller
{
    public function info()
    {
        $metalTrader = MetalTrader::select(
                'id','name','api_key','dealing_group_id','kimi_account_id',
                'phone','balance','market_opening_notification','aggregated_view_of_invoices'
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
