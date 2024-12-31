<?php

namespace App\Http\Resources\Task\TaskTimeLog;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllTaskTimeLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'taskTimeLogId' => $this->id,
            'startAt' => Carbon::parse($this->start_at)->format('d/m/Y H:i'),
            'endAt' => $this->end_at != null ? Carbon::parse($this->end_at)->format('d/m/Y H:i') : "",
            'taskId' => $this->task_id,
            'userId' => $this->user_id,
            'type' => $this->type,
            'comment' => $this->comment??"",
            'status' => $this->status
        ];
    }
}
