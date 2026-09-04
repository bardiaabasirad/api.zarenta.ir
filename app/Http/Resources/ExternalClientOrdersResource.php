<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Morilog\Jalali\Jalalian;

class ExternalClientOrdersResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->status == 'rejected') {
            $msg = $this->extra_data['message'] ?? null;
            $message = $msg
                ? "سفارش شما به علت {$msg} رد شد"
                : 'سفارش شما رد شد';
        } else {
            $message = 'سفارش شما با موفقیت ثبت شد و در صف بررسی قرار دارد';
        }

        return [
            'tracking_code' => $this->tracking_code,
            'product_name'  => $this->product['name'],
            'order_type'    => $this->order_type,
            'status'        => $this->status,
            'quantity'      => $this->product['quantity'],
            'fee'           => $this->product['fee'],
            'message'       => $message,
            'created_at'    => Jalalian::fromCarbon($this->created_at)->format('Y/m/d H:i:s'),
        ];
    }
}
