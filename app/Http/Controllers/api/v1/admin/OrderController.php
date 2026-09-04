<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderDepositRequest;
use App\Http\Requests\OrderUpdateRequest;
use App\Models\Option;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\ShippingMethod;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $data = Order::query();
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');
        $status = request()->input('status');
        $code = request()->input('code');
        $user = request()->input('user');
        $start_date = request()->input('start_date');
        $end_date = request()->input('end_date');

        $data = $data->select('id','code','status','user_id','preferred_shipping_method','created_at')
            ->with('user','items')
            ->when(isset($status) && $status !== 'all', function ($query) use ($status){
                $query->where('status', $status);
            })
            ->when(isset($code), function ($query) use ($code){
                $query->where('id', 'like', '%' .$code . '%')->orWhere('code', 'like', '%' .$code . '%');
            })
            ->when(isset($user), function ($query) use ($user){
                $query->whereHas('user', function($query) use ($user){
                    $query->where('full_name', 'like', '%' . $user . '%');
                });
            })
            ->when(isset($start_date) && isset($end_date), function ($query) use ($start_date, $end_date){
                $startDate = Carbon::parse($start_date)->startOfDay();
                $endDate = Carbon::parse($end_date)->endOfDay();
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->orderBy($sortBy??'created_at', $dir??'desc')
            ->paginate($count??config('app.per_page'));

        return response()->json([
            'data' => $data
        ]);
    }
    public function show($code)
    {
        $order = Order::where('code', $code)->with([
            'items',
            'user',
            'metalOrders'
        ])->firstOrFail();

        $transactions = Transaction::latest()->with('gateway')->where('order_id', $order->id)->get();

        return response()->json([
            'data' => $order,
            'transactions' => $transactions,
            'cancel_reasons' => Option::where('key', 'cancel_order')->get(),
            'payment_methods' => Option::where('key', 'payment_method')->get(),
            'shipping_methods' => ShippingMethod::all(),
            'logs' => $this->log($order->id),
            'last_change' => OrderLog::where('order_id', $order->id)->with(['loggable' => function($query){$query->select('id', 'full_name');}])->orderBy('created_at', 'desc')->first()
        ]);
    }
    public function update(OrderUpdateRequest $request, Order $order)
    {
        $order->updateWithLogging($request->validated());

        return response()->json([
            'order' => $order->load(['items','user','metalOrders']),
            'logs' => $this->log($order->id),
            'last_change' => OrderLog::where('order_id', $order->id)->with(['loggable' => function($query){$query->select('id', 'full_name');}])->orderBy('created_at', 'desc')->first(),
            'message' => 'سفارش با موفقیت ویرایش شد'
        ]);
    }
    private function log($order_id)
    {
        $logs = OrderLog::where('order_id', $order_id)
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

        return collect($result)->map(function ($day) {

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
    }

    public function deposit(OrderDepositRequest $request, Order $order)
    {
        try {
            DB::beginTransaction();
            $transaction = new Transaction();
            $transaction->order_id = $order->id;
            $transaction->amount = $request->amount;
            $transaction->status = 'SUCCESSFUL';
            $transaction->details = [
                'admin' => auth()->user()->only('id', 'full_name'),
                'pay_by' => $request->pay_by,
                'tracking_code' => $request->tracking_code,
                'description' => $request->description,
            ];
            $transaction->save();

            $attributes = [
                'total_paid' => (int)$order->total_paid + (int)$request->amount
            ];

            // Check condition and update status without saving
            if (($order->total_paid + (int)$request->amount) >= ($order->sale + $order->shipping_cost)) {
                $attributes['status'] = OrderStatus::COLLECTING;
                $lastChange = OrderLog::where('order_id', $order->id)->with(['loggable' => function($query){$query->select('id', 'full_name');}])->orderBy('created_at', 'desc')->first();
            }

            $order->updateWithLogging($attributes);
            DB::commit();

            return response()->json([
                'message' => 'مبلغ پرداختی با موفقیت در سیستم ثبت شد',
                'order' => $order,
                'last_change' => $lastChange??null,
                'transaction' => $transaction,
            ]);
        }
        catch (\Exception $e){
            DB::rollBack();

            return response()->json([
                'message' => 'خطایی رخ داد',
                'error' => $e,
            ], 500);
        }
    }
}
