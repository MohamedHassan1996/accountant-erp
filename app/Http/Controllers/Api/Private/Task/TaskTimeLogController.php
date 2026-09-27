<?php

namespace App\Http\Controllers\Api\Private\Task;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\TaskTimeLog\CreateTaskTimeLogRequest;
use App\Http\Resources\Task\TaskTimeLog\AllTaskTimeLogResource;
use App\Http\Resources\Task\TaskTimeLog\TaskTimeLogResource;
use App\Services\Task\TaskTimeLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class TaskTimeLogController extends Controller
{
    protected $taskTimeLogService;

    public function __construct(TaskTimeLogService $taskTimeLogService)
    {
        $this->middleware('auth:api');
        $this->middleware('permission:all_task_time_logs', ['only' => ['index']]);
        $this->middleware('permission:create_task_time_log', ['only' => ['create']]);
        $this->middleware('permission:edit_task_time_log', ['only' => ['edit']]);
        $this->middleware('permission:update_task_time_log', ['only' => ['update']]);
        //$this->middleware('permission:delete_task_time_log', ['only' => ['delete']]);
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

            $validatedData = $createTaskTimeLogRequest->validated();

            $taskTimeLog = $this->taskTimeLogService->recordTimerEvent($validatedData);

            DB::commit();

            return response()->json([
                'message' => __('messages.success.created'),
                'data' => [
                    'taskTimeLogId' => $taskTimeLog->id,
                    'taskId' => $taskTimeLog->task_id,
                ]
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

}
