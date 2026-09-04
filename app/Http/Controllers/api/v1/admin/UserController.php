<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserProductSettingsRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\MarketPrice;
use App\Models\Option;
use App\Models\Order;
use App\Models\User;
use App\Models\UserLog;
use Carbon\Carbon;

class UserController extends Controller
{
    public function index()
    {
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');
        $code = request()->input('code');
        $full_name = request()->input('full_name');
        $phone = request()->input('phone');
        $status = request()->input('status');
        $start_date = request()->input('start_date');
        $end_date = request()->input('end_date');

        $users = User::query();

        $users = $users->select('id','full_name','phone','status','last_seen')
            ->when(isset($status) && $status !== 'all', function ($query) use ($status){
                $query->where('status', $status);
            })
            ->when(isset($code), function ($query) use ($code){
                $query->where('id', 'like', '%' .$code . '%');
            })
            ->when(isset($full_name), function ($query) use ($full_name){
                $query->where('full_name', 'like', '%' . $full_name . '%');
            })
            ->when(isset($phone), function ($query) use ($phone){
                $query->where('phone', 'like', '%' . $phone . '%');
            })
            ->when(isset($start_date) && isset($end_date), function ($query) use ($start_date, $end_date){
                $startDate = Carbon::parse($start_date)->startOfDay();
                $endDate = Carbon::parse($end_date)->endOfDay();
                $query->whereBetween('last_seen', [$startDate, $endDate]);
            })
            ->orderBy($sortBy??'created_at', $dir??'desc')
            ->paginate($count??config('app.per_page'));

        return response()->json($users);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $attributes = $request->validated();

        // Update the broker and log the changes with the description
        $user->updateWithLogging($attributes);

        return response()->json([
            'user' => $user->load([
                'checkedNationalCodes' => function ($query) {
                    $query->where('status', 0)->select('national_code','phone');
                }
            ]),
            'message' => 'کاربر با موفقیت ویرایش شد',
        ]);
    }

    public function show(User $user)
    {
        $cartItems = CartItem::where('user_id', $user->id)->with([
            'product.size_unit',
            'variety' => function($query){
                $query->with('color','images');
            }
        ])->get();

        $latestUpdatedAt = CartItem::where('user_id', $user->id)->orderBy('updated_at', 'desc')->first('updated_at');

        return response()->json([
            'user' => $user->load([
                'checkedNationalCodes' => function ($query) {
                    $query->where('status', 0)->select('national_code','phone');
                }
            ]),
            'market_price' => MarketPrice::latest()->first(),
            'cart_items' => $cartItems,
            'latest_updated_at' => $latestUpdatedAt?$latestUpdatedAt->updated_at:null,
            'increase_reasons' => Option::where('key', 'increase_inventory_count')->get(),
            'decrease_reasons' => Option::where('key', 'decrease_inventory_count')->get(),
            'order_counts' => Order::where('user_id', $user->id)->count(),
            'address_counts' => Address::where('user_id', $user->id)->count(),
            'cartItems_counts' => CartItem::where('user_id', $user->id)->count(),
            'ban_reasons' => Option::where('key', 'ban_user')->get(),
        ]);
    }

    public function addresses($user_id)
    {
        return response()->json(Address::where('user_id', $user_id)->with('city.province')->get());
    }

    public function orders($user_id)
    {
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');
        $status = request()->input('status');
        $code = request()->input('code');
        $start_date = request()->input('start_date');
        $end_date = request()->input('end_date');

        $orders = Order::where('user_id', $user_id)
            ->when(isset($status) && $status !== 'all', function ($query) use ($status){
                $query->where('status', $status);
            })
            ->when(isset($code), function ($query) use ($code){
                $query->where('id', 'like', '%' .$code . '%');
            })
            ->when(isset($start_date) && isset($end_date), function ($query) use ($start_date, $end_date){
                $startDate = Carbon::parse($start_date)->startOfDay();
                $endDate = Carbon::parse($end_date)->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->with('items')
            ->orderBy($sortBy??'created_at', $dir??'desc')
            ->paginate($count??config('app.per_page'));

        return response()->json($orders);
    }

    public function log($user_id)
    {
        $logs = UserLog::where('user_id', $user_id)
            ->with(['loggable' => function($query){
                $query->select('id', 'full_name');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        $groupedLogs = [];

        foreach ($logs as $log) {
            $day = $log->created_at->format('Y-m-d');

            if (!isset($groupedLogs[$day])) {
                $groupedLogs[$day] = (object)[
                    'day' => $day,
                    'flow' => []
                ];
            }

            $groupedLogs[$day]->flow[] = $log;
        }

        $result = array_values($groupedLogs);

        $transformedData = collect($result)->map(function ($day) {

            $day->flow = collect($day->flow)->groupBy(function ($flow) {
                return substr($flow['created_at'], 11, 5); // Extract the hour and minute
            })->map(function ($flows, $hour) {
                return [
                    'hour' => $hour,
                    'flows' => $flows,
                ];
            })->values()->all();

            return $day;

        })->values()->all();

        return response()->json($transformedData);
    }

    public function updateProductSettings(UpdateUserProductSettingsRequest $request, User $user)
    {
        // Get the current products_settings as an array
        $currentSettings = $user->products_settings ?? [];

        // Get only the validated data from the request
        $newSettings = $request->validated();

        // Merge the new settings with the current settings
        // This will update existing keys and add new ones while preserving others
        $updatedSettings = array_merge($currentSettings, $newSettings);

        // Update the client using the custom updateWithLogging method
        $user->update([
            'products_settings' => $updatedSettings
        ]);

        return response()->json([
            'message' => 'اطلاعات کلاینت با موفقیت بروزرسانی شد',
            'user' => $user
        ]);
    }
}
