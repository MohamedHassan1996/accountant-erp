<?php

namespace App\Http\Resources\Client\ClientServiceDiscount;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientServiceDiscountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //dd($this->countries->toArray());
        return [
            'clientServiceDiscountId' => $this->id,
            'serviceCategoryId' => $this->service_category_id,
            'discount' => $this->discount,
            'category'=>$this->category,
            'type' => $this->type,
            'isActive' => $this->is_active,
            'isShow' =>$this->is_show
        ];

    }
}
