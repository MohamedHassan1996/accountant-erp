<?php

namespace App\Http\Resources\Task;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
            'userId' => $this->user_id,
            'clientId' => $this->client_id,
            'serviceCategoryId' => $this->service_category_id,
            'invoiceId' => $this->invoice_id??"",
        ];

    }
}
