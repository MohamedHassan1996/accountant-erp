<?php

namespace App\Http\Resources\Task;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'taskId' => $this->title,
            'title' => $this->title,
            'status' => $this->status,
            'accountantName' => $this->user->name,
            'clientName' => $this->client->ragione_sociale,
            'serviceCategoryName' => $this->serviceCategory->name,
        ];
    }
}
