<?php

namespace App\Http\Controllers\Api\Private\Task;

use App\Enums\Task\TaskStatus;
use App\Enums\Task\TaskTimeLogStatus;
use App\Http\Controllers\Controller;
use App\Models\Task\Task;
use App\Models\Task\TaskTimeLog;
use App\Services\Task\TaskTimeLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ChangeTaskTimeLogController extends Controller
{
    protected $taskTimeLogService;

    public function __construct(TaskTimeLogService $taskTimeLogService)
    {
        $this->middleware('auth:api');
        $this->middleware('permission:change_task_time_log', ['only' => ['update']]);
        //$this->middleware('permission:delete_task_time_log', ['only' => ['delete']]);
        $this->taskTimeLogService = $taskTimeLogService;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {

        $data = $request->validate([
            'taskId' => ['nullable', 'required_without:taskTimeLogId', 'integer'],
            'taskTimeLogId' => ['nullable', 'integer'],
            'totalTime' => ['required', 'string', 'regex:/\A[0-9]{2,4}:[0-5][0-9]:[0-5][0-9]\z/'],
            'comment' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($data) {
            $taskId = $data['taskId'] ?? TaskTimeLog::findOrFail($data['taskTimeLogId'])->task_id;
            $task = Task::lockForUpdate()->findOrFail($taskId);
            $taskTimeLogs = $task->timeLogs()->lockForUpdate();

            // Reuse the latest log when the frontend has no log ID, including retries.
            $taskTimeLog = !empty($data['taskTimeLogId'])
                ? $taskTimeLogs->findOrFail($data['taskTimeLogId'])
                : $taskTimeLogs->latest('id')->first();

            if ($taskTimeLog && $taskTimeLog->status != TaskTimeLogStatus::STOP) {
                return response()->json([
                    'message' => "you can't change this task time log",
                ]);
            }

            if ($taskTimeLog) {
                $taskTimeLog->update([
                    'total_time' => $data['totalTime'],
                    'comment' => $data['comment'] ?? null,
                ]);
                $task->update(['status' => TaskStatus::DONE->value]);
            } else {
                $this->taskTimeLogService->createCompletedTimeLogs($task, $data['totalTime'], $data['comment'] ?? null);
            }

            return response()->json([
                 'message' => __('messages.success.updated')
            ], 200);
        });


    }


}
