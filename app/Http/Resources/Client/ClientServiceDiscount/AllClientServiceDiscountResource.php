<?php

namespace App\Http\Resources\Client\ClientServiceDiscount;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllClientServiceDiscountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'clientServiceDiscountId' => $this->id,
            'serviceCategoryName' => $this->serviceCategory->name,
            'discount' => $this->discount,
            'type' => $this->type,
            'isActive' => $this->is_active,
            'isShow' => $this->is_show,
        ];
    }
}
