<?php

namespace App\Http\Controllers\Api\Private\Task;

use App\Enums\Task\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\TaskTimeLog\UpdateTaskTimeLogRequest;
use App\Http\Requests\Task\TaskTimeLog\CreateTaskTimeLogRequest;
use App\Http\Resources\Task\TaskTimeLog\AllTaskTimeLogResource;
use App\Http\Resources\Task\TaskTimeLog\TaskTimeLogResource;
use App\Models\Task\Task;
use App\Services\Task\TaskTimeLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class TaskTimeLogController extends Controller
{
    protected $taskTimeLogService;

    public function __construct(TaskTimeLogService $taskTimeLogService)
    {
        $this->middleware('auth:api');
        // $this->middleware('permission:all_time_log_tasks', ['only' => ['index']]);
        // $this->middleware('permission:create_time_log', ['only' => ['create']]);
        // $this->middleware('permission:edit_time_log', ['only' => ['edit']]);
        // $this->middleware('permission:update_time_log', ['only' => ['update']]);
        // $this->middleware('permission:delete_time_log', ['only' => ['delete']]);
        $this->taskTimeLogService = $taskTimeLogService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $allTimeLogs = $this->taskTimeLogService->allTaskTimeLogs($request->all());

        return AllTaskTimeLogResource::collection($allTimeLogs);
    }

    /**
     * Show the form for creating a new resource.
     */

    public function create(CreateTaskTimeLogRequest $createTaskTimeLogRequest)
    {

        try {
            DB::beginTransaction();

            $taskTimeLog = $this->taskTimeLogService->createTaskTimeLog($createTaskTimeLogRequest->validated());

            $task = Task::find($createTaskTimeLogRequest->taskId);

            if($task->timeLogs()->count() == 1) {
                $task->update([
                    'status' => TaskStatus::IN_PROGRESS->value
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => __('messages.success.created')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }


    }

    /**
     * Show the form for editing the specified resource.
     */

    public function edit(Request $request)
    {
        $taskTimeLog  =  $this->taskTimeLogService->editTaskTimeLog($request->taskTimeLogId);

        return new TaskTimeLogResource($taskTimeLog);


    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskTimeLogRequest $updateTaskTimeLogRequest)
    {

        try {
            DB::beginTransaction();
            $this->taskTimeLogService->updateTaskTimeLog($updateTaskTimeLogRequest->validated());
            DB::commit();
            return response()->json([
                 'message' => __('messages.success.updated')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }


    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request)
    {

        try {
            DB::beginTransaction();
            $this->taskTimeLogService->deleteTaskTimeLog($request->taskTimeLogId);
            DB::commit();
            return response()->json([
                'message' => __('messages.success.deleted')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }


    }

}
