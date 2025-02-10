<?php

namespace App\Services\Task;

use App\Enums\Task\TaskStatus;
use App\Enums\Task\TaskTimeLogStatus;
use App\Enums\Task\TaskTimeLogType;
use App\Filters\TaskTimeLog\FilterTaskTimeLog;
use App\Models\Task\Task;
use App\Models\Task\TaskTimeLog;
use Carbon\Carbon;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaskTimeLogService{

    public function allTaskTimeLogs(array $filters){

        $taskTimeLogs = QueryBuilder::for(TaskTimeLog::class)
        ->allowedFilters([
            //AllowedFilter::custom('search', new FilterTaskTimeLog()), // Add a custom search filter
        ])
        ->where('task_id', $filters['taskId'])
        ->get();
        return $taskTimeLogs;

    }

    public function createTaskTimeLog(array $taskTimeLogData){

        $task = Task::find($taskTimeLogData['taskId']);
        if($task->timeLogs()->count() > 0) {
            $latestTaskTimeLog = $task->timeLogs()->latest()->first();
            if($latestTaskTimeLog->type == TaskTimeLogType::TIME_LOG->value && $latestTaskTimeLog->status == TaskTimeLogStatus::START->value) {
                $totalTime = $taskTimeLogData['startAt']->diffInMinutes($latestTaskTimeLog->start_at);
                $latestTaskTimeLog->update([
                    'status' => TaskTimeLogStatus::PAUSE->value,
                    'end_at' => $taskTimeLogData['startAt'],
                    'total_time' => $totalTime
                ]);
            }
        }

        $taskTimeLog = TaskTimeLog::create([
            'start_at' => $taskTimeLogData['startAt'],
            'end_at' => $taskTimeLogData['endAt']??null,
            'type' => TaskTimeLogType::from($taskTimeLogData['type'])->value,
            'comment' => $taskTimeLogData['comment']??null,
            'task_id' => $taskTimeLogData['taskId'],
            'user_id' => $taskTimeLogData['userId'],
            'status' => TaskTimeLogStatus::from($taskTimeLogData['status'])->value,
        ]);

        if($taskTimeLogData['status'] == TaskTimeLogStatus::STOP->value) {
            $task->closed_at = $taskTimeLogData['endAt'];
            $task->save();
        }

        return $taskTimeLog;

    }

    public function editTaskTimeLog(string $taskTimeLogId){
        $taskTimeLog = TaskTimeLog::find($taskTimeLogId);

        return $taskTimeLog;

    }

    public function updateTaskTimeLog(array $taskTimeLogData){

        $taskTimeLog = TaskTimeLog::find($taskTimeLogData['taskTimeLogId']);

        $totalTime = 0;

        if(isset($taskTimeLogData['endAt'])) {
            $totalTime = Carbon::parse($taskTimeLogData['endAt'])->diffInMinutes($taskTimeLog->start_at);
        }

        $startDate = $taskTimeLog->start_at;

        $status = $taskTimeLog->status == TaskTimeLogStatus::PAUSE && $taskTimeLogData['status'] == TaskTimeLogStatus::STOP ? $taskTimeLog->status : TaskTimeLogStatus::from($taskTimeLogData['status'])->value;

        $taskTimeLog->fill([
            'start_at' => $startDate,
            'end_at' => $taskTimeLogData['endAt']??null,
            'type' => TaskTimeLogType::from($taskTimeLogData['type'])->value,
            'comment' => $taskTimeLogData['comment']??null,
            'task_id' => $taskTimeLogData['taskId'],
            'user_id' => $taskTimeLogData['userId'],
            'status' => $status,
            'total_time' => $totalTime
        ]);

        $taskTimeLog->save();

        if($taskTimeLogData['status'] == TaskTimeLogStatus::STOP->value) {
            $task = Task::find($taskTimeLog->task_id);
            $task->status = TaskStatus::DONE->value;
            $task->closed_at = $taskTimeLogData['endAt']??null;
            $task->save();
        }

        return $taskTimeLog;

    }

    public function deleteTaskTimeLog(string $taskTimeLogId){
        $taskTimeLog = TaskTimeLog::find($taskTimeLogId);
        $taskTimeLog->delete();
    }

}
