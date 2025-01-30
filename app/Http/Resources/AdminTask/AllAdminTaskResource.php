<?php

namespace App\Http\Resources\AdminTask;

use Carbon\Carbon;
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
            'createdAt' => Carbon::parse($this->created_at)->format('d/m/Y'),
            'startDate' => $this->start_date?Carbon::parse($this->start_at)->format('d/m/Y'):"",
            'endDate' => $this->end_date?Carbon::parse($this->end_date)->format('d/m/Y'):"",
            "startTime"=>$this->timeLogs()->first()?Carbon::parse($this->timeLogs()->first()->start_at)->format('d/m/Y H:i:s') : "",
            "endTime"=>$this->timeLogs()->latest()->first()?Carbon::parse($this->timeLogs()->latest()->first()->end_at)->format('d/m/Y H:i:s'):"",
        ];
    }
}
