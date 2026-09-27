<?php

namespace App\Http\Controllers\Api\Private\Task;

use App\Enums\Task\TaskStatus;
use App\Enums\Task\TaskTimeLogStatus;
use App\Enums\Task\TaskTimeLogType;
use App\Http\Controllers\Controller;
use App\Models\Task\Task;
use App\Models\Task\TaskTimeLog;
use App\Services\Task\TaskTimeLogService;
use App\Utils\PaginateCollection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class ActiveTaskController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('permission:all_active_tasks', ['only' => ['index']]);
        $this->middleware('permission:update_active_task', ['only' => ['create']]);
        // $this->middleware('permission:edit_task', ['only' => ['edit']]);
        // $this->middleware('permission:update_task', ['only' => ['update']]);
        // $this->middleware('permission:delete_task', ['only' => ['delete']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::guard('api')->user();
        $tasks = DB::table('tasks')
        ->join('clients', 'tasks.client_id', '=', 'clients.id')
        ->where('tasks.user_id', $user->id)
        ->whereNull('tasks.deleted_at')
        ->whereIn('tasks.status', [
            TaskStatus::TO_WORK->value,
            TaskStatus::IN_PROGRESS->value
        ])
        ->select([
            'tasks.id as taskId',
            'tasks.title',
            'tasks.status',
            'clients.id as clientId',
            'clients.ragione_sociale as clientName',
        ])
        ->whereNull('tasks.deleted_at')
        ->get();

        foreach ($tasks as $index => $task) {
            if($task->status == TaskStatus::TO_WORK->value) {
                $tasks[$index]->totalTime = 0;
                $tasks[$index]->time = '00:00:00';
                $tasks[$index]->timerStatus = 0;
            }

            if ($task->status == TaskStatus::IN_PROGRESS->value) {
                $taskModel = Task::findOrFail($task->taskId);
                $latestLog = $taskModel->timeLogs()->where('type', TaskTimeLogType::TIME_LOG->value)
                    ->latest('id')->first();
                $time = $taskModel->current_time;
                [$hours, $minutes, $seconds] = array_map('intval', explode(':', $time));
                $tasks[$index]->totalTime = ($hours * 3600) + ($minutes * 60) + $seconds;
                $tasks[$index]->time = $time;
                $tasks[$index]->timerStatus = !$latestLog ? 0
                    : ($latestLog->status === TaskTimeLogStatus::START ? 1 : 2);
                $tasks[$index]->timeLogId = $latestLog?->id ?? '';
            }

        }
        return response()->json(
            $tasks
        );
    }

    // /**
    //  * Show the form for creating a new resource.
    //  */

    // public function create(CreateTaskRequest $createTaskRequest)
    // {

    //     try {
    //         DB::beginTransaction();

    //         $this->taskService->createTask($createTaskRequest->validated());

    //         DB::commit();

    //         return response()->json([
    //             'message' => __('messages.success.created')
    //         ], 200);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }


    // }

    // /**
    //  * Show the form for editing the specified resource.
    //  */

    // public function edit(Request $request)
    // {
    //     $task  =  $this->taskService->editTask($request->taskId);

    //     return new TaskResource($task);


    // }

    // /**
    //  * Update the specified resource in storage.
    //  */
    public function update(Request $request, TaskTimeLogService $taskTimeLogService)
    {

        try {

            DB::beginTransaction();
            $taskTimeLog = TaskTimeLog::findOrFail($request->taskTimeLogId);
            $status = $request->taskStatus == TaskStatus::DONE->value
                ? TaskTimeLogStatus::STOP
                : ($request->filled('endAt') ? TaskTimeLogStatus::PAUSE : TaskTimeLogStatus::START);
            $taskTimeLogService->recordTimerEvent([
                'taskId' => $taskTimeLog->task_id,
                'userId' => $taskTimeLog->user_id,
                'type' => TaskTimeLogType::TIME_LOG->value,
                'status' => $status->value,
                'currentTime' => $taskTimeLog->total_time,
                'endAt' => $request->endAt,
            ]);

            DB::commit();
            return response()->json([
                 'message' => __('messages.success.updated')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }


    }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function delete(Request $request)
    // {

    //     try {
    //         DB::beginTransaction();
    //         $this->taskService->deleteTask($request->taskId);
    //         DB::commit();
    //         return response()->json([
    //             'message' => __('messages.success.deleted')
    //         ], 200);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }


    // }

}
