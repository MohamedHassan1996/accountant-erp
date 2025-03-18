<?php

namespace App\Services\Task;

use App\Models\Task\Task;
use App\Enums\Task\TaskStatus;
use App\Enums\Task\TaskTimeLogStatus;
use App\Enums\Task\TaskTimeLogType;
use App\Filters\Task\FilterTask;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Filters\Task\FilterTaskDateBetween;
use App\Filters\Task\FilterTaskStartEndDate;
use App\Models\Client\ClientServiceDiscount;
use App\Models\ServiceCategory\ServiceCategory;
use App\Models\Task\TaskTimeLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TaskService{

    public function allTasks()
    {
        $filters = request()->input('filter', []);
        $startDate = $filters['startDate'] ?? null;
        $endDate = $filters['endDate'] ?? null;
        $pageSize = request()->input('pageSize', 10); // Default 10 if not provided
        $page = request()->input('page', 1); // Get the requested page

        // Query without calling `get()`
        $query = QueryBuilder::for(Task::class)
            ->allowedFilters([
                AllowedFilter::custom('search', new FilterTask()),
                AllowedFilter::exact('userId', 'user_id'),
                AllowedFilter::exact('status', 'status'),
                AllowedFilter::exact('serviceCategoryId', 'service_category_id'),
                AllowedFilter::exact('clientId', 'client_id'),
                AllowedFilter::exact('userId', 'user_id'),
            ])
            ->when(
                !empty($startDate) && !empty($endDate),
                fn($query) => $query->whereDate('created_at', '>=', $startDate)
                                    ->whereDate('created_at', '<=', $endDate)
            )
            ->when(
                !empty($endDate) && empty($startDate),
                fn($query) => $query->whereDate('created_at', '<=', $endDate)
            )
            ->when(
                empty($endDate) && !empty($startDate),
                fn($query) => $query->whereDate('created_at', '=', $startDate)
            )
            ->where('is_new', 1)
            ->orderByDesc('id');


        // Get total count before pagination
        $totalTasks = $query->count();

        // Apply manual pagination
        $tasks = $query->skip(($page - 1) * $pageSize)->take($pageSize)->get();

        // Get IDs only for the current page
        $taskIds = $tasks->pluck('id');


        // Calculate total time for only paginated tasks
        /*$totalTime = DB::table('task_time_logs')
            /*->whereIn('task_id', $taskIds)
            ->sum('total_time');*/

            // Get latest time logs for each task
        $latestLogs = DB::table('task_time_logs as ttl')
            ->join(
                DB::raw('(SELECT task_id, MAX(created_at) as latest FROM task_time_logs GROUP BY task_id) as latest_logs'),
                function ($join) {
                    $join->on('ttl.task_id', '=', 'latest_logs.task_id')
                         ->on('ttl.created_at', '=', 'latest_logs.latest');
                }
            )
        ->whereIn('ttl.task_id', $taskIds)
        ->get();


        $sumTotalTime = 0;

        foreach ($latestLogs as $index => $logs) {
            $latestLog = $logs; // Get the latest log for the task
            if ($latestLog->status == 0) { // Task is in play status
                $createdAt = Carbon::parse($latestLog->created_at);
                $now = Carbon::now();
                $elapsedTime = $now->diffInSeconds($createdAt);

                if ($latestLog->total_time == '00:00:00') {
                    $sumTotalTime += $elapsedTime;
                } else {
                    $sumTotalTime += $elapsedTime + Carbon::parse($latestLog->total_time)->diffInSeconds(Carbon::parse('00:00:00'));
                }
            } else {


                $sumTotalTime += Carbon::parse($latestLog->total_time)->diffInSeconds(Carbon::parse('00:00:00'));

                // if($index == 13){
                //     dd($sumTotalTime);
                // }
                //                                     //dd($sumTotalTime);

            }
        }


        // Convert seconds to "H:i:s" format with total hours
        $totalHours = floor($sumTotalTime / 3600);
        $minutes = floor(($sumTotalTime % 3600) / 60);
        $remainingSeconds = $sumTotalTime % 60;
        $formattedTotalTime = sprintf('%d:%02d:%02d', $totalHours, $minutes, $remainingSeconds);

        return [
            'tasks' => $tasks,
            'totalTime' => $formattedTotalTime
        ];
    }

    public function createTask(array $taskData){
        $task = Task::create([
            'title' => $taskData['title']??"",
            'description' => $taskData['description']??"",
            'client_id' => $taskData['clientId'],
            'user_id' => $taskData['userId'],
            'service_category_id' => $taskData['serviceCategoryId'],
            'invoice_id' => $taskData['invoiceId']??null,
            'status' => TaskStatus::from($taskData['status'])->value,
            'connection_type_id' => $taskData['connectionTypeId']??null,
            'start_date' => $taskData['startDate']??null,
            'end_date' => $taskData['endDate']??null,
        ]);

        return $task;

    }

    public function editTask(string $taskId){
        $task = Task::with('timeLogs')->find($taskId);
        // $startTask= $task->timeLogs->start_at ;
        // $endTask=$task->timeLogs->end_at;
        return $task;

    }

    public function updateTask(array $taskData){

        $task = Task::find($taskData['taskId']);

        if($task->status == TaskStatus::DONE) {
            return response()->json([
                'message' => 'Task is already done',
            ], 401);
        }

        $task->fill([
            'title' => $taskData['title']??"",
            'description' => $taskData['description']??"",
            'client_id' => $taskData['clientId'],
            'user_id' => $taskData['userId'],
            'service_category_id' => $taskData['serviceCategoryId'],
            'invoice_id' => $taskData['invoiceId']??null,
            'status' => TaskStatus::from($taskData['status'])->value,
            'connection_type_id' => $taskData['connectionTypeId']??null,
            'start_date' => $taskData['startDate']??null,
            'end_date' => $taskData['endDate']??null
        ]);

        // if($taskData['status'] == TaskStatus::DONE->value) {

        //     //$latestTimeLog = TaskTimeLog::find($taskData['taskTimeLogId']);

        //     $taskTimeLogs = $task->timeLogs()->latest()->first();

        //     $totalTime = $taskData['currentTime'];

        //     /*if(!empty($latestTimeLog)){
        //         $totalTime = gmdate('H:i:s', Carbon::now()->diffInSeconds($latestTimeLog->created_at));
        //     }*/

        //     if(count($taskTimeLogs) == 1 && $taskTimeLogs[0]->status->value == TaskTimeLogStatus::START->value){
        //         $taskTimeLog = TaskTimeLog::create([
        //             'start_at' => null,
        //             'end_at' => null,
        //             'type' => TaskTimeLogType::TIME_LOG->value,
        //             'comment' => null,
        //             'task_id' => $task->id,
        //             'user_id' => $taskData['userId'],
        //             'status' => TaskTimeLogStatus::STOP->value,
        //             'total_time' => $totalTime,
        //         ]);
        //     }




        // }



        $task->save();

        return $task;

    }

    public function deleteTask(string $taskId){
        $task = Task::find($taskId);
        $task->delete();
    }
    public function changeStatus(string $taskId, int $status){
        $task = Task::find($taskId);
        $task->update([
            'status' => TaskStatus::from($status)->value
        ]);
    }

}
