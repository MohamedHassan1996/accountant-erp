<?php

namespace App\Services\Task;

use App\Models\Task\Task;
use App\Enums\Task\TaskStatus;
use App\Filters\Task\FilterTask;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Filters\Task\FilterTaskDateBetween;
use App\Filters\Task\FilterTaskStartEndDate;
use App\Models\Client\ClientServiceDiscount;
use App\Models\ServiceCategory\ServiceCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TaskService{

    public function allTasks()
    {
        $filters = request()->input('filter', []);
        $startDate = $filters['startDate'] ?? null;
        $endDate = $filters['endDate'] ?? null;

        //dd($startDate, $endDate);

        $tasks = QueryBuilder::for(Task::class)
            ->allowedFilters([
                AllowedFilter::custom('search', new FilterTask()), // Custom search filter
                AllowedFilter::exact('userId', 'user_id'),
                AllowedFilter::exact('status', 'status'),
                AllowedFilter::exact('serviceCategoryId', 'service_category_id'),
                AllowedFilter::exact('clientId', 'client_id'),
                AllowedFilter::exact('userId', 'user_id'),
            ])
            ->when(
                !empty($startDate) && !empty($endDate),
                function ($query) use ($startDate, $endDate) {
                    $query->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate);
                }
            )
            ->when(
                !empty($endDate) && empty($startDate),
                function ($query) use ($endDate) {

                    $query->whereDate('created_at', '<=', $endDate);
                }
            )
            ->when(
                empty($endDate) && !empty($startDate),
                function ($query) use ($startDate) {

                    $query->whereDate('created_at', '=', $startDate);
                }
            )
            ->where('is_new', 1)
            ->orderByDesc('id')
            ->get();

                    // Get IDs of filtered tasks
        $taskIds = $tasks->pluck('id');

        // Calculate total time for filtered tasks
        $totalTime = DB::table('task_time_logs')
            ->whereIn('task_id', $taskIds)
            ->sum('total_time');

        // Get latest time logs for each task
        $latestLogs = DB::table('task_time_logs')
            ->whereIn('task_id', $taskIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('task_id');

        $sumTotalTime = 0;

        foreach ($latestLogs as $taskId => $logs) {
            $latestLog = $logs->first(); // Get the latest log for the task

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

        // Convert sumTotalTime from seconds to H:i:s format
        $formattedTotalTime = gmdate('H:i:s', $sumTotalTime);

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
