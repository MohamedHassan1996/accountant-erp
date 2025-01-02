<?php

namespace App\Http\Resources\Task;

use Carbon\Carbon;
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
            'taskId' => $this->id,
            'title' => $this->title,
            'number' => $this->number,
            'status' => $this->status,
            'accountantName' => $this->user->full_name,
            'clientName' => $this->client->ragione_sociale,
            'serviceCategoryName' => $this->serviceCategory->name,
            'totalHours' => $this->total_hours,
            'createdAt' => Carbon::parse($this->created_at)->format('d/m/Y'),
            'startDate' => $this->start_date?Carbon::parse($this->start_at)->format('d/m/Y'):"",
            'endDate' => $this->end_date?Carbon::parse($this->end_date)->format('d/m/Y'):"",
        ];
    }
}
