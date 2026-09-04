<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MetalTraderStoreRequest;
use App\Http\Requests\MetalTraderUpdateRequest;
use App\Http\Requests\SubscriptionStoreRequest;
use App\Http\Requests\SubscriptionUpdateRequest;
use App\Http\Requests\UpdateProductSettingsRequest;
use App\Jobs\SmsJob;
use App\Models\BlockReason;
use App\Models\DealingGroup;
use App\Models\MetalItem;
use App\Models\MetalTrader;
use App\Models\MetalTraderLead;
use App\Models\MetalTraderLog;
use App\Models\Subscription;
use App\Models\SubscriptionFeature;
use App\Models\VerificationCode;
use App\Services\KimiaService;
use App\Services\MetalTraderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MetalTraderController extends Controller
{
    public function index()
    {
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');
        $id = request()->input('id');
        $name = request()->input('name');
        $phone = request()->input('phone');
        $status = request()->input('status');
        $dealingGroupId = request()->input('dealing_group_id');

        $metalTraders = MetalTrader::query();

        $metalTraders = $metalTraders->select('id','dealing_group_id', 'name','phone','last_seen','status')
            ->when(isset($id), function ($query) use ($id){
                $query->where('id', 'like', '%' .$id . '%');
            })
            ->when(isset($name), function ($query) use ($name){
                $query->where('name', 'like', '%' . $name . '%');
            })
            ->when(isset($dealingGroupId), function ($query) use ($dealingGroupId){
                $query->where('dealing_group_id', $dealingGroupId);
            })
            ->when(isset($status) && $status !== 'all', function ($query) use ($status){
                $query->where('status', $status);
            })
            ->when(isset($phone), function ($query) use ($phone){
                $query->where('phone', 'like', '%' . $phone . '%');
            })
            ->orderBy($sortBy??'created_at', $dir??'desc')
            ->paginate($count??config('app.per_page'));

        return response()->json([
            'metal_traders' => $metalTraders
        ]);
    }

    public function leads()
    {
        $sortBy = request()->input('sortBy');
        $dir = request()->input('dir');
        $count = request()->input('count');
        $id = request()->input('id');
        $phone = request()->input('phone');
        $lead_type = request()->input('lead_type');

        $metalTraders = MetalTraderLead::query();

        $metalTraders = $metalTraders->select('id','phone','last_attempt_at','lead_type','created_at')
            ->when(isset($id), function ($query) use ($id){
                $query->where('id', 'like', '%' .$id . '%');
            })
            ->when(isset($phone), function ($query) use ($phone){
                $query->where('phone', 'like', '%' . $phone . '%');
            })
            ->when(isset($lead_type) && $lead_type !== 'all', function ($query) use ($lead_type){
                $query->where('lead_type', $lead_type);
            })
            ->orderBy($sortBy??'created_at', $dir??'desc')
            ->paginate($count??config('app.per_page'));

        return response()->json([
            'metal_trader_leads' => $metalTraders
        ]);
    }

    public function create()
    {
        return response()->json([
            'dealing_groups' => DealingGroup::select('id', 'name')->get(),
        ]);
    }

    public function store(MetalTraderStoreRequest $request, MetalTraderService $metalTraderService)
    {
        $attributes = $request->validated();
        $attributes['api_key'] = $metalTraderService->generateApiKey();
        $attributes['status'] = 'active';
        $metalTrader = MetalTrader::create($attributes);

        $metalTraderService->notify($metalTrader);

        return response()->json([
            'client' => $metalTrader,
            'message' => 'کلاینت جدید با موفقیت ایجاد شد'
        ]);
    }

    public function update(MetalTraderUpdateRequest $request, MetalTrader $metal_trader, MetalTraderService $service)
    {
        // Update the broker and log the changes with the description
        $webhookSecret = $service->updateWithLogging(
            $metal_trader,
            $request->validated()
        );

        $response = [
            'metal_trader' => $metal_trader->fresh()->load([
                'subscriptions' => function ($query) {
                    $query->where('ends_at', '>', now());
                },
                'dealingGroup'
            ]),
            'message' => 'اطلاعات کلاینت با موفقیت بروزرسانی شد',
        ];

        if ($webhookSecret !== null) {
            $response['webhook_setup'] = [
                'secret' => $webhookSecret,
                'secret_version' => 'v1',
                'message' => 'کلید اختصاصی امضای وب‌هوک با موفقیت تولید شد. این کلید تنها یک‌بار نمایش داده می‌شود. آن را کپی کرده و از طریق یک کانال امن در اختیار کاربر قرار دهید.',
            ];
        }

        return response()->json($response);
    }

    public function updateProductSettings(UpdateProductSettingsRequest $request, MetalTrader $metal_trader)
    {
        // Get the current products_settings as an array
        $currentSettings = $metal_trader->products_settings ?? [];

        // Get only the validated data from the request
        $newSettings = $request->validated();

        // Merge the new settings with the current settings
        // This will update existing keys and add new ones while preserving others
        $updatedSettings = array_merge($currentSettings, $newSettings);

        // Update the client using the custom updateWithLogging method
        $metal_trader->update([
            'products_settings' => $updatedSettings
        ]);

        return response()->json([
            'message' => 'اطلاعات کلاینت با موفقیت بروزرسانی شد',
            'client' => $metal_trader
        ]);
    }

    public function updateSubscription(SubscriptionUpdateRequest $request, Subscription $subscription)
    {
        // Parse dates
        if($request->starts_at){
            $subscription->starts_at = Carbon::createFromFormat('Y-m-d', $request->starts_at);
        }

        if($request->ends_at) {
            $subscription->ends_at = Carbon::createFromFormat('Y-m-d', $request->ends_at);
        }

        if(isset($request->amount)){
            $subscription->amount = $request->amount;
        }

        // Attach features to the subscription
        if ($request->features) {
            $subscription->subscriptionFeatures()->sync($request->features);
        }

        $subscription->save();

        return response()->json([
            'subscription' => $subscription->load('subscriptionFeatures'),
            'message' => 'اطلاعات کلاینت با موفقیت بروزرسانی شد',
        ]);
    }

    public function show(MetalTrader $metal_trader)
    {
        $metal_trader->load([
            'subscriptions' => function ($query) {
                $query->where('ends_at', '>', now());
            },
            'dealingGroup:id,name'
        ]);

        $latestVerificationCode = VerificationCode::where('authenticatable_type', 'App\Models\MetalTrader')
            ->where('phone', $metal_trader->phone)
            ->latest()
            ->first();

        if ($latestVerificationCode) {
            $latestVerificationCode->makeVisible('code');
        }

        return response()->json([
            'metal_trader' => $metal_trader,
            'metal_items' => MetalItem::withoutGlobalScope('visible')->get(['id', 'kimia_product_id', 'unit']),
            'latest_verification_code' => $latestVerificationCode,
            'dealing_groups' => DealingGroup::select('id', 'name')->get(),
            'block_reasons' => BlockReason::where('type','clients')->get(),
        ]);
    }

    public function balance(MetalTrader $metal_trader)
    {
        $aggregated = request('aggregated') ?? 'inactive';

        return response()->json(KimiaService::getVoucherBalance($metal_trader->kimi_account_id, $aggregated));
    }

    public function log($metal_trader_id)
    {
        $logs = MetalTraderLog::where('metal_trader_id', $metal_trader_id)
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

    public function subscriptions($metal_trader_id)
    {
        $subscriptions = Subscription::with('subscriptionFeatures')->where('metal_trader_id', $metal_trader_id)->get();

        return response()->json([
            'subscriptions' => $subscriptions
        ]);
    }

    public function createSubscription(MetalTrader $metal_trader)
    {
        return response()->json([
            'client' => $metal_trader,
            'features' => SubscriptionFeature::all()
        ]);
    }

    public function storeSubscriptions(MetalTrader $metal_trader, SubscriptionStoreRequest $request)
    {
        try {
            // Parse dates
            $start_date = Carbon::createFromFormat('Y-m-d', $request->start_date);
            $ends_date = Carbon::createFromFormat('Y-m-d', $request->ends_date);

            // Use a database transaction to ensure atomicity
            $subscription = DB::transaction(function () use ($metal_trader, $start_date, $ends_date, $request) {
                // Create the subscription
                $subscription = $metal_trader->subscriptions()->create([
                    'starts_at' => $start_date,
                    'ends_at' => $ends_date,
                    'amount' => $request->amount,
                ]);

                // Attach features to the subscription
                if ($request->features) {
                    $subscription->subscriptionFeatures()->attach($request->features);
                }

                return $subscription;
            });

            // Return a JSON response
            return response()->json([
                'message' => 'اشتراک جدید با موفقیت ایجاد شد',
                'subscription' => $subscription->load('subscriptionFeatures')
            ]);
        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
            return response()->json([
                'error' => 'Invalid date format. Use YYYY-MM-DD.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function showSubscription(Subscription $subscription)
    {
        $subscription->load('subscriptionFeatures');
        $client = MetalTrader::where('id', $subscription->metal_trader_id)->first();

        return response()->json([
            'client' => $client,
            'subscription' => $subscription,
            'features' => SubscriptionFeature::all()
        ]);
    }

    public function searchKimiAccount()
    {
        $query = trim(request()->get('q', ''));

        if ($query === '') {
            return response()->json([
                'account' => null
            ]);
        }

        $accounts = KimiaService::getAccounts([
            'AccountId' => $query,
        ]);

        $accounts = is_array($accounts) ? $accounts : [];

        return response()->json([
            'account' => $accounts[0] ?? null
        ]);
    }
}
