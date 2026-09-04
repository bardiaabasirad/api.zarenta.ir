<?php

namespace App\Jobs;

use App\Models\MarketPrice;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetMarketPriceJob
{
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     * @throws Exception
     */
    public function handle(): void
    {
        try {
            sleep(20);

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
            }
        }catch (Exception $e){
            // فقط خطاهای غیر از مشکلات شبکه را لاگ کن
            $message = strtolower($e->getMessage());

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
