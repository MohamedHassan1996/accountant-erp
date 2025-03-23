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

    /*public function allTasks()
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

            }
        }


        // Convert seconds to "H:i:s" format with total hours
        $totalHours = floor($sumTotalTime / 3600);
        $minutes = floor(($sumTotalTime % 3600) / 60);
        $remainingSeconds = $sumTotalTime % 60;
        $formattedTotalTime = sprintf('%d:%02d:%02d', $totalHours, $minutes, $remainingSeconds);

        return [
            'tasks' => $tasks,
            'totalTime' => $formattedTotalTime,
            'total' => $totalTasks
        ];
    }*/

    public function allTasks()
{
    $filters = request()->input('filter', []);
    $startDate = $filters['startDate'] ?? null;
    $endDate = $filters['endDate'] ?? null;
    $pageSize = request()->input('pageSize', 10);

    // Build Query with Filtering
    $query = QueryBuilder::for(Task::class)
        ->allowedFilters([
            AllowedFilter::custom('search', new FilterTask()),
            AllowedFilter::exact('userId', 'user_id'),
            AllowedFilter::exact('status', 'status'),
            AllowedFilter::exact('serviceCategoryId', 'service_category_id'),
            AllowedFilter::exact('clientId', 'client_id'),
        ])
        ->when($startDate && $endDate, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
        ->when($endDate && !$startDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
        ->when($startDate && !$endDate, fn($q) => $q->whereDate('created_at', '=', $startDate))
        ->where('is_new', 1)
        ->orderByDesc('id');

    // Get Paginated Data
    $tasks = $query->paginate($pageSize);
    $taskIds = $tasks->pluck('id');

    // Fetch Latest Logs for Each Task
    $latestLogs = DB::table('task_time_logs as ttl')
        ->join(
            DB::raw('(SELECT task_id, MAX(created_at) as latest FROM task_time_logs GROUP BY task_id) as latest_logs'),
            fn($join) => $join->on('ttl.task_id', '=', 'latest_logs.task_id')
                              ->on('ttl.created_at', '=', 'latest_logs.latest')
        )
        ->whereIn('ttl.task_id', $taskIds)
        ->get();

    // Compute Total Time
    $sumTotalTime = 0;
    foreach ($latestLogs as $log) {
        $createdAt = Carbon::parse($log->created_at);
        $elapsedTime = Carbon::now()->diffInSeconds($createdAt);

        if ($log->status == 0) { // Task is active
            $sumTotalTime += ($log->total_time === '00:00:00')
                ? $elapsedTime
                : $elapsedTime + Carbon::parse($log->total_time)->diffInSeconds('00:00:00');
        } else {
            $sumTotalTime += Carbon::parse($log->total_time)->diffInSeconds('00:00:00');
        }
    }

    // Convert to "H:i:s" format with total hours continuing beyond 24
    $formattedTotalTime = sprintf('%d:%02d:%02d', floor($sumTotalTime / 3600), ($sumTotalTime % 3600) / 60, $sumTotalTime % 60);

    return [
        'tasks' => $tasks,
        'totalTime' => $formattedTotalTime,
        'total' => $tasks->total(),
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

        /*if($task->status == TaskStatus::DONE) {
            return response()->json([
                'message' => 'Task is already done',
            ], 401);
        }*/

        // $task->fill([
        //     'title' => $taskData['title']??"",
        //     'description' => $taskData['description']??"",
        //     'client_id' => $taskData['clientId'],
        //     'user_id' => $taskData['userId'],
        //     'service_category_id' => $taskData['serviceCategoryId'],
        //     'invoice_id' => $taskData['invoiceId']??null,
        //     'status' => TaskStatus::from($taskData['status'])->value,
        //     'connection_type_id' => $taskData['connectionTypeId']??null,
        //     'start_date' => $taskData['startDate']??null,
        //     'end_date' => $taskData['endDate']??null
        // ]);

        $task->title = $taskData['title']??"";
        $task->description = $taskData['description']??"";
        $task->client_id = $taskData['clientId'];
        $task->user_id = $taskData['userId'];
        $task->service_category_id = $taskData['serviceCategoryId'];
        $task->invoice_id = $taskData['invoiceId']??null;
        $task->connection_type_id = $taskData['connectionTypeId']??null;
        $task->start_date = $taskData['startDate']??null;
        $task->end_date = $taskData['endDate']??null;

        if($task->status != TaskStatus::DONE){
            $task->status = TaskStatus::from($taskData['status'])->value;
        }

        /*return response()->json([
                'message' => 'Task is already done',
            ], 401);*/

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
