<?php

namespace App\Http\Resources\AdminTask;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllAdminTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'taskId' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'number' => $this->number,
            'accountantName' => $this->user->full_name,
            'clientName' => $this->client->ragione_sociale,
            'serviceCategoryName' => $this->serviceCategory->name,
            'totalHours' => $this->total_hours,
            'costOfService' => $this->serviceCategory->getPrice(),
            'costAfterDiscount' => $this->getTotalPriceAfterDiscountAttribute(),
        ];
    }
}
