<?php

namespace App\Http\Resources\Task;

use Carbon\Carbon;
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
            'number' => $this->number,
            'status' => $this->status,
            'userId' => $this->user_id,
            'clientId' => $this->client_id,
            'serviceCategoryId' => $this->service_category_id,
            'invoiceId' => $this->invoice_id??"",
            'timeLogStatus' => $this->timeLogStatus,
            'currentTime' => $this->current_time,
            'latestTimeLogId' => $this->latest_time_log_id,
            'connectionTypeId' => $this->connection_type_id,
            'startDate' => $this->start_date??"",
            'endDate' => $this->end_date??"",
            'startTime' => $this->timeLogs()?Carbon::parse($this->timeLogs()->latest()->first()->start_at)->format('d/m/Y H:i:s'):Carbon::now(),
            'endTime' => $this->timeLogs()?Carbon::parse($this->timeLogs()->latest()->first()->end_at)->format('d/m/Y H:i:s'):Carbon::now(),

        ];

    }
}
