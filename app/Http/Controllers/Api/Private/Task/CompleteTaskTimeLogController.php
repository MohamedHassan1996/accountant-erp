<?php

namespace App\Http\Controllers\Api\Private\Task;

use App\Enums\Task\TaskStatus;
use App\Http\Controllers\Controller;
use App\Services\Task\TaskTimeLogService;
use Illuminate\Http\Request;

class CompleteTaskTimeLogController extends Controller
{
    public function __construct(protected TaskTimeLogService $taskTimeLogService)
    {
        $this->middleware('auth:api');
        $this->middleware('permission:complete-ticket-with-time');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ticketId' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
            'totalTime' => ['required', 'string', 'regex:/\A[0-9]{2,4}:[0-5][0-9]:[0-5][0-9]\z/'],
        ]);

        $log = $this->taskTimeLogService->completeTicket(
            $data['ticketId'],
            $data['totalTime'],
            $data['note'] ?? null,
        );

        return response()->json([
            'message' => __('messages.success.updated'),
            'data' => [
                'ticketId' => $log->task_id,
                'taskTimeLogId' => $log->id,
                'status' => TaskStatus::DONE->value,
                'timeLogStatus' => $log->status->value,
                'totalTime' => $log->total_time,
                'note' => $log->comment ?? '',
            ],
        ]);
    }
}
