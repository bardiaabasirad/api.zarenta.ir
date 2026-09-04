<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Events\OrderUpdated;
use App\Jobs\SmsJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class Order extends Model
{
    protected $fillable = [
        'status',
        'melted',
        'cancellation_reason',
        'cancellation_details',
        'selected_shipping_method',
        'tracking_code',
        'delivery_details',
        'total_paid'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function metalOrders()
    {
        return $this->morphMany(MetalOrder::class, 'creator', 'created_type', 'created_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected $casts = [
        'address' => 'json',
        'preferred_shipping_method' => 'json',
        'selected_shipping_method' => 'json',
    ];

    public function scopeAcceptedInCalculations($query)
    {
        return $query->whereIn('status', [OrderStatus::PAID, OrderStatus::RESERVED, OrderStatus::COLLECTING, OrderStatus::COLLECTED, OrderStatus::DELIVERED]);
    }

    public function updateWithLogging($attributes)
    {
        // Decode the selected_shipping_method if it's a JSON string
        if (isset($attributes['selected_shipping_method']) && is_string($attributes['selected_shipping_method'])) {
            $attributes['selected_shipping_method'] = json_decode($attributes['selected_shipping_method']);
        }

        // Retrieve the original attribute values of the model before any changes
        $original = $this->getOriginal();

        // Set the new attribute values on the model, but do not save yet
        $this->fill($attributes);

        // Determine which attributes have been modified since the last sync
        $dirty = $this->getDirty();

        // Save the new attribute values to the database
        $this->update($attributes);

        // Check if the order status has changed to canceled
        if (array_key_exists('status', $dirty) && $this->status === 'canceled') {
            $this->incrementVarietyCounts();
        }

        // Check if the order status has changed to fire order update event
        if (array_key_exists('status', $dirty)) {
            try {
                $this->notifyToUser();

                $orderData = [
                    'id' => $this->id,
                    'code' => $this->code,
                    'status' => $this->status,
                    'user_id' => $this->user_id,
                    'sale' => $this->sale,
                    'purchase' => $this->purchase,
                    'preferred_shipping_method' => $this->preferred_shipping_method,
                    'user' => $this->user
                ];

                event(new OrderUpdated($orderData));
            }
            catch (\Exception $e) {}
        }

        // Calculate which attributes have actually changed after the update
        // by comparing the dirty attributes with the original ones
        $changedAttributes = array_intersect_key($original, $dirty);

        // Get changes made to the model, excluding 'updated_at' and 'selected_shipping_method'.
        // If 'selected_shipping_method' is present in the changes, include it.
        $changes = Arr::except($this->getChanges(), ['updated_at', 'selected_shipping_method']);

        // If 'selected_shipping_method' is provided, add it back to the changes (potentially overriding existing value).
        if (isset($attributes['selected_shipping_method'])) {
            $changes['selected_shipping_method'] = $attributes['selected_shipping_method'];
        }

        // If any attributes have been changed, proceed to log the changes
        if ($this->wasChanged()) {
            // Create a new log entry with the broker's ID, the ID and class type of the user who made the changes,
            // the new values of the changed attributes, the original values of those attributes, and the description of the changes

            OrderLog::create([
                'order_id' => $this->id,
                'loggable_id' => Auth::id(),
                'loggable_type' => get_class(auth()->user()),
                'new_values' => $changes,
                'old_values' => $changedAttributes,
            ]);
        }
    }

    private function notifyToUSer(){
        $this->load('user');
        if ($this->status == OrderStatus::COLLECTING || $this->status == OrderStatus::COLLECTED || $this->status == OrderStatus::DELIVERED) {
            switch ($this->status) {
                case OrderStatus::COLLECTING:
                    // سفارش تایید شد
                    $sendNotificationMessageWhenOrderConfirmed = Setting::where('option_key', 'send_notification_message_when_order_confirmed')->first();
                    if ($sendNotificationMessageWhenOrderConfirmed->option_value == 'active'){
                        SmsJob::dispatch(
                            $this->user->phone,
                            "w45r2ozc9skkfju",
                            [
                                'id' => "$this->code"
                            ]
                        )->onConnection('sync');
                    }
                    break;
                case OrderStatus::COLLECTED:
                    // سفارش آماده تحویل یا ارسال
                    if ($this->preferred_shipping_method){
                        $sendNotificationMessageWhenOrderReadyToSend = Setting::where('option_key', 'send_notification_message_when_order_ready_to_sent')->first();
                        if ($sendNotificationMessageWhenOrderReadyToSend->option_value == 'active'){
                            SmsJob::dispatch(
                                $this->user->phone,
                                "nlk66fcedfheym3",
                                [
                                    'id' => "$this->code"
                                ]
                            )->onConnection('sync');
                        }
                    }
                    else {
                        $sendNotificationMessageWhenOrderReadyForDelivery = Setting::where('option_key', 'send_notification_message_when_order_ready_for_delivery')->first();
                        if ($sendNotificationMessageWhenOrderReadyForDelivery->option_value == 'active'){
                            SmsJob::dispatch(
                                $this->user->phone,
                                "dq8aeecgiilzi5h",
                                [
                                    'id' => "$this->code"
                                ]
                            )->onConnection('sync');
                        }
                    }
                    break;
                case OrderStatus::DELIVERED:
                    // سفارش ارسال شد
                    if ($this->preferred_shipping_method){
                        $sendNotificationMessageWhenOrderHasBeenSend = Setting::where('option_key', 'send_notification_message_when_order_has_been_sent')->first();
                        if ($sendNotificationMessageWhenOrderHasBeenSend->option_value == 'active'){
                            SmsJob::dispatch(
                                $this->user->phone,
                                "e1b9rx749hyh6as",
                                [
                                    'id' => "$this->code"
                                ]
                            )->onConnection('sync');
                        }
                    }
                    break;
            }
        }
    }

    /**
     * @return void
     */
    private function incrementVarietyCounts(): void
    {
        // Iterate over each order item and increment the variety count
        foreach ($this->items as $orderItem) {
            $variety = $orderItem->variety;

            $varietyLog = new VarietyLog();
            $varietyLog->variety_id = $variety->id;
            $varietyLog->old_values = ['count' => (string)$variety->count];
            $varietyLog->new_values = ['count' => (string)($variety->count + (int)$orderItem->count)];
            $varietyLog->details = ['order_id' => (string)$this->id];
            $varietyLog->save();

            $variety->increment('count', $orderItem->count);
        }
    }
}
