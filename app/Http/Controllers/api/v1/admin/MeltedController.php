<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Constants\AppConstants;
use App\Http\Controllers\Controller;
use App\Http\Resources\RateResource;
use App\Models\SelectedMetalPrice;
use App\Models\PriceSource;
use App\Models\PriceSourceMapping;
use App\Models\Setting;
use App\Models\RawMetalPrice;
use App\Services\SelectedMetalPriceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MeltedController extends Controller
{
    public function channels()
    {
        $reference_channels = PriceSource::select('id','channel_id','channel_name','open','close','work_with')
            ->with('market')
            ->orderBy('ordering')
            ->get();

        return response()->json([
            'references' => $reference_channels,
        ]);
    }

    public function settings()
    {
        $channels = PriceSource::orderBy('ordering')->get();

        $javadHanzaeiFilePath = app_path('Scripts/javadhanzaeigold/cookies.json');
        $mohammadHanzaeiFilePath = app_path('Scripts/mohammadhanzaeigold/cookies.json');

        $javadHanzaeiCookieContent = "";
        if (file_exists($javadHanzaeiFilePath)) {
            $javadHanzaeiCookieContent = file_get_contents($javadHanzaeiFilePath);
        }

        $mohammadHanzaeiCookieContent = "";
        if (file_exists($mohammadHanzaeiFilePath)) {
            $mohammadHanzaeiCookieContent = file_get_contents($mohammadHanzaeiFilePath);
        }

        // افزودن محتوای کوکی به فقط همان Channel
        $channels->transform(function ($channel) use ($javadHanzaeiCookieContent, $mohammadHanzaeiCookieContent) {
            if ($channel->channel_id === 'javadhanzaeigold') {
                $channel->cookie = $javadHanzaeiCookieContent;
            }
            else if ($channel->channel_id === 'mohammadhanzaeigold') {
                $channel->cookie = $mohammadHanzaeiCookieContent;
            }
            return $channel;
        });

        return response()->json([
            'channels' => $channels
        ]);
    }

    public function updateCookie($cookie, $channel_id)
    {
        switch ($channel_id){
            case 'javadhanzaeigold':
                $filePath = app_path('Scripts/javadhanzaeigold/cookies.json');
                break;
            case 'mohammadhanzaeigold':
                $filePath = app_path('Scripts/mohammadhanzaeigold/cookies.json');
                break;
            default:
                $filePath = '';
        }

        // تبدیل داده به رشته JSON خوش‌خوان
        $jsonData = $cookie;

        // اگر پوشه وجود نداشت بساز
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        // نوشتن در فایل
        file_put_contents($filePath, $jsonData);

        return response()->json([
            'message' => 'فایل cookies.json با موفقیت بروزرسانی شد ✅'
        ]);
    }

    public function rates()
    {
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $channel = request()->input('channel');
        $start_date = request()->input('start_date');
        $end_date = request()->input('end_date');

        $telMarketPrices = RawMetalPrice::query();

        $telMarketPrices = $telMarketPrices->select('id','reference_channel_id','buy','sell','time','created_at')
            ->where('type', 'tomorrow')
            ->when(isset($channel) && $channel !== 'all', function ($query) use ($channel){
                $query->where('reference_channel_id', $channel);
            })
            ->when(isset($start_date) && isset($end_date), function ($query) use ($start_date, $end_date){
                $startDate = Carbon::parse($start_date)->startOfDay();
                $endDate = Carbon::parse($end_date)->endOfDay();
                $query->whereBetween('time', [$startDate, $endDate]);
            })
            ->with('reference:id,channel_name')
            ->orderBy($sortBy??'created_at', $dir??'desc')
            ->limit(50)->get();

        // Add the 'delay' attribute to each market price
        $telMarketPrices->transform(function ($marketPrice) {
            $time = Carbon::parse($marketPrice->time, 'Asia/Tehran');
            $created_at = Carbon::parse($marketPrice->created_at, 'Asia/Tehran');
            $marketPrice->delay = abs($created_at->diffInSeconds($time));
            return $marketPrice;
        });

        return response()->json([
            'data' => $telMarketPrices
        ]);
    }

    public function update(PriceSource $channel, Request $request)
    {
        // بروزرسانی فقط فیلدهای مجاز
        $channel->fill(
            $request->only(['channel_name'])
        );

        // بروزرسانی کوکی فقط برای کانال‌های مجاز
        if ($request->filled('cookie') && in_array($channel->channel_id, [
                'javadhanzaeigold',
                'mohammadhanzaeigold',
            ], true)) {
            $this->updateCookie($request->cookie, $channel->channel_id);
        }

        $channel->save();

        return response()->json([
            'reference' => $channel,
            'message'   => 'بروزرسانی با موفقیت انجام شد',
        ]);
    }
}
