<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetalOrdersResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $extraData = $this->extra_data ?? [];
        $timezone  = config('app.timezone');
        $status    = $this->status;

        return [
            'tracking_code' => $this->tracking_code,
            'order_type'    => $this->order_type,
            'status'        => $status,
            'product'       => $this->product,

            'message' => $status === 'rejected'
                ? ($extraData['message'] ?? '')
                : '',

            'frozen'     => $extraData['frozen'] ?? null,
            'created_at' => $this->created_at->timezone($timezone)->format('Y-m-d H:i:s'),
            'now'        => Carbon::now($timezone)->format('Y-m-d H:i:s'),
        ];
    }
}
