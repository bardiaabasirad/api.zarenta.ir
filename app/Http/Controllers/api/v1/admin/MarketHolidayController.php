<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\MarketHoliday;
use Illuminate\Http\Request;
use Morilog\Jalali\Jalalian;

class MarketHolidayController extends Controller
{
    // دریافت کل تعطیلات (یا بازه مشخص مثلا سال جاری)
    public function index()
    {
        $holidays = MarketHoliday::orderBy('date', 'asc')->get();
        return response()->json($holidays);
    }

    // تاگل کردن یک روز (اگر بود حذف کند، اگر نبود بسازد)
    public function toggle(Request $request)
    {
        $request->validate([
            'jalali_date' => 'required|regex:/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}$/'
        ]);

        $jalaliStr = $request->input('jalali_date');
        $carbonDate = Jalalian::fromFormat('Y/m/d', $jalaliStr)->toCarbon();

        // جمعه در Carbon برابر با 5 است (Friday)
        if ($carbonDate->isFriday()) {
            return response()->json([
                'message' => 'روزهای جمعه به طور خودکار تعطیل هستند و نیازی به ثبت دستی ندارند.'
            ], 422);
        }

        $gregorianDateStr = $carbonDate->format('Y-m-d');
        $holiday = MarketHoliday::where('date', $gregorianDateStr)->first();

        if ($holiday) {
            $holiday->delete();
            return response()->json(['status' => 'removed', 'jalali_date' => $jalaliStr]);
        }

        $created = MarketHoliday::create([
            'date' => $gregorianDateStr,
            'jalali_date' => $jalaliStr,
            'title' => $request->input('title', 'تعطیلی بازار')
        ]);

        return response()->json(['status' => 'added', 'data' => $created]);
    }

    // حذف صریح از جدول
    public function destroy($id)
    {
        MarketHoliday::findOrFail($id)->delete();
        return response()->json(['message' => 'روز تعطیل با موفقیت حذف شد.']);
    }
}
