<?php

namespace App\Http\Controllers;

use App\Models\MarketPrice;
use App\Models\MetalItem;
use App\Models\MetalItemGroup;
use App\Models\MetalOrder;
use App\Models\Setting;
use App\Services\Hamtala\HamtalaOrderExchangeService;
use App\Services\Hamtala\HamtalaPriceService;
use App\Services\KimiaService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ManualController extends Controller
{
    public function __construct(
        private readonly HamtalaOrderExchangeService $hamtalaOrderExchangeService,
        private readonly HamtalaPriceService $service
    ) {}

    public function postTest(Request $request)
    {
        Log::info('req', $request->all());
    }

    public function test()
    {
        $minAndMax = Setting::whereIn('option_key', [
            'min_melted_stock_quantity',
            'max_melted_stock_quantity'
        ])->pluck('option_value', 'option_key');

        return $minAndMax['max_melted_stock_quantity'];
        $baseUrl  = config('services.kimia_api.base_url');
        $username = config('services.kimia_api.username');
        $password = config('services.kimia_api.password');

        return [
            $baseUrl,
            $username,
            $password
        ];

        $metalItem = MetalItem::with('group')->find(5);

        return $metalItem->group->id == 1 ? 'yes':'no';

        return KimiaService::getProductCurrencies();

//        MetalOrder::where([
//            'source_order_id', $source_order_id,
//            'created_type', (new MetalTrader())->getMorphClass(),
//            'created_id', $metal_trader_id
//        ])->first();
//        $metalOrder = MetalOrder::find(14369);
//        $this->hamtalaOrderExchangeService->submitAutoOrder($metalOrder);
    }

    public function setNewTaban(Request $request)
    {
        $marketPrice = new MarketPrice();
        $marketPrice->ounce = $request->OunceTala??null;
        $marketPrice->price = $request->YekGram18;
        $marketPrice->emam_coin = $request->SekehEmam??null;
        $marketPrice->full_coin = $request->SekehTamam??null;
        $marketPrice->half_coin = $request->SekehNim??null;
        $marketPrice->quarter_coin = $request->SekehRob??null;
        $marketPrice->dollar = $request->Dollar??null;
        $marketPrice->euro = $request->Euro??null;
        $marketPrice->derham = $request->Derham??null;
        $marketPrice->read_at = Carbon::createFromFormat('Y/m/d H:i:s', $request->TimeRead);
        $marketPrice->save();
    }

    public function refreshJibitToken()
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])
            ->post('https://napi.jibit.ir/ide/v1/tokens/generate', [
                'apiKey' => config('app.jibit_api_key'),
                'secretKey' => config('app.jibit_secret_key'),
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                Setting::where('option_key', 'access_token')->update([
                    'option_value' => $responseData['accessToken'],
                ]);

                Setting::where('option_key', 'refresh_token')->update([
                    'option_value' => $responseData['refreshToken'],
                ]);
            }

            return 'OK';
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }

    public function getMarketPrice()
    {
        try {
            // Make the external API call (using Guzzle or Http facade)
            $response = Http::get('https://webservice.tgnsrv.ir/Pr/Get/zareie3475/z09123753475z');
            // Parse the response (assuming it's JSON)
            $data = $response->json();

            if (isset($data['YekGram18'])) {
                $marketPrice = new MarketPrice();
                $marketPrice->ounce = $data['OunceTala']??null;
                $marketPrice->price = $data['YekGram18'];
                $marketPrice->emam_coin = $data['SekehEmam']??null;
                $marketPrice->full_coin = $data['SekehTamam']??null;
                $marketPrice->half_coin = $data['SekehNim']??null;
                $marketPrice->quarter_coin = $data['SekehRob']??null;
                $marketPrice->dollar = $data['Dollar']??null;
                $marketPrice->euro = $data['Euro']??null;
                $marketPrice->derham = $data['Derham']??null;
                $marketPrice->read_at = Carbon::createFromFormat('Y/m/d H:i:s', $data['TimeRead']);
                $marketPrice->save();

                return $marketPrice;
            }

            return $data['YekGram18'];
        }catch (Exception $e){
            // فقط خطاهای غیر از مشکلات شبکه را لاگ کن
            $message = strtolower($e->getMessage());

            return $message;

            // لیست کلمات کلیدی که نباید لاگ شوند
            $ignoreKeywords = ['timeout', 'timed out', 'connection', 'curl error'];

            $shouldLog = true;
            foreach ($ignoreKeywords as $keyword) {
                if (str_contains($message, $keyword)) {
                    $shouldLog = false;
                    break;
                }
            }

            if ($shouldLog) {
                Log::error('Getting Taban API Error', [
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
