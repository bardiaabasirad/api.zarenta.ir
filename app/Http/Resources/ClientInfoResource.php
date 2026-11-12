<?php

namespace App\Http\Resources;
use App\Models\DealingGroup;
use App\Services\EncryptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $encryptedDealingGroup = EncryptionService::encrypt($this->dealingGroup);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'group' => $encryptedDealingGroup,
            'has_pass' => (bool) $this->has_pass,
            'market_opening_notification' => $this->market_opening_notification,
        ];
    }
}
