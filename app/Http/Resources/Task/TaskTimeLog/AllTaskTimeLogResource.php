<?php

namespace App\Http\Resources\Task\TaskTimeLog;

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
            'startAt' => $this->start_at,
            'endAt' => $this->end_at,
            'taskId' => $this->task_id,
            'userId' => $this->user_id,
            'type' => $this->type,
            'comment' => $this->comment,
            'timeLogId' => $this->time_log_id??null,
        ];
    }
}
