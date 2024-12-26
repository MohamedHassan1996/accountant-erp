<?php

namespace App\Services\Task;

use App\Enums\Task\TaskTimeLogType;
use App\Filters\TaskTimeLog\FilterTaskTimeLog;
use App\Models\Task\TaskTimeLog;
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

        $taskTimeLog = TaskTimeLog::create([
            'start_at' => $taskTimeLogData['startAt'],
            'end_at' => $taskTimeLogData['endAt']??null,
            'type' => TaskTimeLogType::from($taskTimeLogData['type'])->value,
            'comment' => $taskTimeLogData['comment']??null,
            'task_id' => $taskTimeLogData['taskId'],
            'time_log_id' => $taskTimeLogData['timeLogId']??null,
            'user_id' => $taskTimeLogData['userId']

        ]);


        return $taskTimeLog;

    }

    public function editTaskTimeLog(string $taskTimeLogId){
        $taskTimeLog = TaskTimeLog::find($taskTimeLogId);

        return $taskTimeLog;

    }

    public function updateTaskTimeLog(array $taskTimeLogData){

        $taskTimeLog = TaskTimeLog::find($taskTimeLogData['taskTimeLogId']);

        $taskTimeLog->fill([
            'start_at' => $taskTimeLogData['startAt'],
            'end_at' => $taskTimeLogData['endAt']??null,
            'type' => TaskTimeLogType::from($taskTimeLogData['type'])->value,
            'comment' => $taskTimeLogData['comment']??null,
            'task_id' => $taskTimeLogData['taskId'],
            'time_log_id' => $taskTimeLogData['timeLogId']??null,
            'user_id' => $taskTimeLogData['userId']
        ]);

        $taskTimeLog->save();

        return $taskTimeLog;

    }

    public function deleteTaskTimeLog(string $taskTimeLogId){
        $taskTimeLog = TaskTimeLog::find($taskTimeLogId);
        $taskTimeLog->delete();
    }

}
