<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\MetalItem;
use App\Models\PriceSource;
use App\Models\RawMetalPrice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RawMetalPriceController extends Controller
{
    public function index()
    {
        $id = request()->input('id');
        $price_source_id = request()->input('price_source_id');
        $metal_item_id = request()->input('metal_item_id');
        $start_date = request()->input('start_date');
        $end_date = request()->input('end_date');

        $raw_metal_prices = RawMetalPrice::query();

        $raw_metal_prices = $raw_metal_prices
            ->when(isset($id), function ($query) use ($id){
                $query->where('id', 'like', '%' .$id . '%');
            })
            ->when(isset($price_source_id) && $price_source_id != 'all', function ($query) use ($price_source_id){
                $query->where('price_source_id', $price_source_id);
            })
            ->when(isset($metal_item_id) && $metal_item_id != 'all', function ($query) use ($metal_item_id){
                $query->where('metal_item_id', $metal_item_id);
            })
            ->when(isset($start_date) && isset($end_date), function ($query) use ($start_date, $end_date){
                $startDate = Carbon::parse($start_date)->startOfDay();
                $endDate = Carbon::parse($end_date)->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->with(['priceSource', 'metalItem' => function ($query) {
                $query->withoutGlobalScopes(['visible']);
            }])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $raw_metal_prices->transform(function ($marketPrice) {
            $time = Carbon::parse($marketPrice->time, 'Asia/Tehran');
            $created_at = Carbon::parse($marketPrice->created_at, 'Asia/Tehran');
            $marketPrice->delay = abs($created_at->diffInSeconds($time));
            return $marketPrice;
        });

        $price_sources = Cache::remember('price_sources', 3600, fn() =>
            PriceSource::select('id','name')->get()
        );

        $metal_items = Cache::remember('metal_items', 3600, fn() =>
            MetalItem::select('id','title')->get()
        );

        return response()->json([
            'raw_metal_prices' => $raw_metal_prices,
            'price_sources' => $price_sources,
            'metal_items' => $metal_items,
        ]);
    }
}
