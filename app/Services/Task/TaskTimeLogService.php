<?php

namespace App\Services\Task;

use App\Enums\Task\TaskStatus;
use App\Enums\Task\TaskTimeLogStatus;
use App\Enums\Task\TaskTimeLogType;
use App\Filters\TaskTimeLog\FilterTaskTimeLog;
use App\Models\Task\Task;
use App\Models\Task\TaskTimeLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
        /*if($task->timeLogs()->count() > 0) {
            $latestTaskTimeLog = $task->timeLogs()->latest()->first();
            if($latestTaskTimeLog->type == TaskTimeLogType::TIME_LOG->value && $latestTaskTimeLog->status == TaskTimeLogStatus::START->value) {
                $totalTime = $taskTimeLogData['startAt']->diffInMinutes($latestTaskTimeLog->start_at);
                $latestTaskTimeLog->update([
                    'status' => TaskTimeLogStatus::PAUSE->value,
                    'end_at' => $taskTimeLogData['startAt'],
                    'total_time' => $totalTime
                ]);
            }
        }*/

        $taskTimeLog = TaskTimeLog::create([
            'start_at' => $taskTimeLogData['startAt']??null,
            'end_at' => $taskTimeLogData['endAt']??null,
            'type' => TaskTimeLogType::from($taskTimeLogData['type'])->value,
            'comment' => $taskTimeLogData['comment']??null,
            'task_id' => $taskTimeLogData['taskId'],
            'user_id' => $taskTimeLogData['userId'],
            'status' => TaskTimeLogStatus::from($taskTimeLogData['status'])->value,
            'total_time' => $taskTimeLogData['currentTime']??"00:00:00"
        ]);

        if($task->timeLogs()->count() == 1) {
            $task->update([
                'status' => TaskStatus::IN_PROGRESS->value
            ]);
        }

        if($taskTimeLogData['status'] == TaskTimeLogStatus::STOP->value) {
            $task->status = TaskStatus::DONE->value;
            $task->save();
        }

        return $taskTimeLog;

    }

    public function createCompletedTimeLogs(Task $task, string $totalTime, ?string $comment = null): TaskTimeLog
    {
        $startLog = $this->createTaskTimeLog([
            'taskId' => $task->id,
            'userId' => $task->user_id,
            'type' => TaskTimeLogType::TIME_LOG->value,
            'status' => TaskTimeLogStatus::START->value,
            'currentTime' => '00:00:00',
            'comment' => null,
        ]);
        $stopLog = $this->createTaskTimeLog([
            'taskId' => $task->id,
            'userId' => $task->user_id,
            'type' => TaskTimeLogType::TIME_LOG->value,
            'status' => TaskTimeLogStatus::STOP->value,
            'currentTime' => $totalTime,
            'comment' => $comment,
        ]);

        // Readers order logs by created_at, which has second precision.
        // Backdate the manual start so STOP remains latest, even for zero time.
        [$hours, $minutes, $seconds] = array_map('intval', explode(':', $totalTime));
        $duration = ($hours * 3600) + ($minutes * 60) + $seconds;
        $startLog->created_at = $stopLog->created_at->copy()->subSeconds(max(1, $duration));
        $startLog->save();

        $task->refresh();

        return $stopLog;
    }

    public function completeTicket(int $ticketId, string $totalTime, ?string $note = null): TaskTimeLog
    {
        return DB::transaction(function () use ($ticketId, $totalTime, $note) {
            $task = Task::lockForUpdate()->findOrFail($ticketId);
            $latestLog = $task->timeLogs()
                ->where('type', TaskTimeLogType::TIME_LOG->value)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (!$latestLog) {
                return $this->createCompletedTimeLogs($task, $totalTime, $note);
            }

            if ($latestLog->status === TaskTimeLogStatus::STOP) {
                $latestLog->update(['total_time' => $totalTime, 'comment' => $note]);
                $task->update(['status' => TaskStatus::DONE->value]);

                return $latestLog;
            }

            // Preserve the START event and close its open session before recording STOP.
            if ($latestLog->status === TaskTimeLogStatus::START && $latestLog->end_at === null) {
                $latestLog->update(['end_at' => now()]);
            }

            $stopLog = $this->createTaskTimeLog([
                'taskId' => $task->id,
                'userId' => $task->user_id,
                'type' => TaskTimeLogType::TIME_LOG->value,
                'status' => TaskTimeLogStatus::STOP->value,
                'currentTime' => $totalTime,
                'comment' => $note,
            ]);

            // Existing readers use created_at alone, so avoid ties on immediate completion.
            if ($stopLog->created_at->lessThanOrEqualTo($latestLog->created_at)) {
                $stopLog->created_at = $latestLog->created_at->copy()->addSecond();
                $stopLog->save();
            }

            return $stopLog;
        });
    }

    public function editTaskTimeLog(string $taskTimeLogId){
        $taskTimeLog = TaskTimeLog::find($taskTimeLogId);

        return $taskTimeLog;

    }

    /*public function updateTaskTimeLog(array $taskTimeLogData){

        $taskTimeLog = TaskTimeLog::find($taskTimeLogData['taskTimeLogId']);

        $totalTime = 0;

        if(isset($taskTimeLogData['endAt'])) {
            $totalTime = Carbon::parse($taskTimeLogData['endAt'])->diffInMinutes($taskTimeLog->start_at);
        }

        $startDate = $taskTimeLog->start_at;

        $status = TaskTimeLogStatus::from($taskTimeLogData['status'])->value;

        $closdAt = $taskTimeLogData['endAt']??null;
        if($taskTimeLog->status == TaskTimeLogStatus::PAUSE && $taskTimeLogData['status'] == TaskTimeLogStatus::STOP->value) {
            $status = $taskTimeLog->status;
            $taskTimeLogData['endAt'] = $taskTimeLog->end_at;
        }

        $taskTimeLog->start_at = $taskTimeLogData['startAt'];
        $taskTimeLog->end_at = $taskTimeLogData['endAt']??null;
        $taskTimeLog->type = TaskTimeLogType::from($taskTimeLogData['type'])->value;
        $taskTimeLog->comment = $taskTimeLogData['comment']??null;
        $taskTimeLog->task_id = $taskTimeLogData['taskId'];
        $taskTimeLog->user_id = $taskTimeLogData['userId'];
        $taskTimeLog->status = $status;
        $taskTimeLog->total_time = $totalTime;

        $taskTimeLog->save();

        if($taskTimeLogData['status'] == TaskTimeLogStatus::STOP->value) {
            $task = Task::find($taskTimeLog->task_id);
            $task->status = TaskStatus::DONE->value;
            $task->save();
        }

        return $taskTimeLog;

    }*/

    public function deleteTaskTimeLog(string $taskTimeLogId){
        $taskTimeLog = TaskTimeLog::find($taskTimeLogId);
        $taskTimeLog->delete();
    }

}
