<?php

namespace Tests\Feature;

use App\Enums\Task\TaskStatus;
use App\Enums\Task\TaskTimeLogStatus;
use App\Enums\Task\TaskTimeLogType;
use App\Models\Task\Task;
use App\Models\Task\TaskTimeLog;
use Illuminate\Support\Facades\DB;
use Tests\Support\TaskTimeLogTestCase;

class ChangeTaskTimeLogTest extends TaskTimeLogTestCase
{
    private const ENDPOINT = '/api/v1/task-time-logs/change-time';

    public function test_empty_log_id_creates_start_then_stop_and_closes_the_task(): void
    {
        $this->freezeTime();
        $task = $this->createTask();

        $this->putJson(self::ENDPOINT, [
            'taskId' => $task->id,
            'taskTimeLogId' => '',
            'totalTime' => '01:01:01',
            'comment' => '',
        ])->assertOk()->assertJson(['message' => __('messages.success.updated')]);

        $logs = $task->timeLogs()->orderBy('id')->get();
        $this->assertCount(2, $logs);
        [$startLog, $log] = $logs->all();
        $this->assertSame(TaskTimeLogStatus::START, $startLog->status);
        $this->assertSame('00:00:00', $startLog->total_time);
        $this->assertSame(TaskTimeLogType::TIME_LOG, $startLog->type);
        $this->assertSame(2, $startLog->user_id);
        $this->assertSame(1, $startLog->created_by);
        $this->assertSame(3661, $startLog->created_at->diffInSeconds($log->created_at));
        $this->assertSame('01:01:01', $log->total_time);
        $this->assertSame(TaskTimeLogStatus::STOP, $log->status);
        $this->assertSame(TaskTimeLogType::TIME_LOG, $log->type);
        $this->assertSame(2, $log->user_id);
        $this->assertSame(1, $log->created_by);
        $this->assertNull($log->comment);
        $this->assertNull($log->start_at);
        $this->assertNull($log->end_at);
        $this->assertSame(TaskStatus::DONE, $task->fresh()->status);
        $this->assertSame('01:01:01', $task->fresh()->total_hours);
        $this->assertSame(TaskTimeLogStatus::STOP->value, $task->fresh()->time_log_status);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_repeated_requests_without_a_log_id_reuse_the_existing_log(): void
    {
        $task = $this->createTask();

        foreach (['01:01:01', '125:30:45'] as $time) {
            $this->putJson(self::ENDPOINT, [
                'taskId' => $task->id,
                'taskTimeLogId' => null,
                'totalTime' => $time,
            ])->assertOk();
        }

        $this->assertSame(2, $task->timeLogs()->count());
        $this->assertSame('00:00:00', $task->timeLogs()->where('status', TaskTimeLogStatus::START->value)->sole()->total_time);
        $this->assertSame('125:30:45', $task->timeLogs()->where('status', TaskTimeLogStatus::STOP->value)->sole()->total_time);
        $this->assertSame('125:30:45', $task->fresh()->total_hours);
        $this->assertSame(TaskStatus::DONE, $task->fresh()->status);
    }

    public function test_zero_duration_still_has_stop_as_the_latest_log(): void
    {
        $this->freezeTime();
        $task = $this->createTask();

        $this->putJson(self::ENDPOINT, [
            'taskId' => $task->id,
            'taskTimeLogId' => '',
            'totalTime' => '00:00:00',
            'comment' => '',
        ])->assertOk();

        $this->travel(5)->seconds();
        $this->assertSame(2, $task->timeLogs()->count());
        $this->assertSame(TaskTimeLogStatus::STOP, $task->timeLogs()->latest()->first()->status);
        $this->assertSame('00:00:00', $task->fresh()->total_hours);
        $this->assertSame('00:00:00', $task->fresh()->current_time);
        $this->assertSame(TaskStatus::DONE, $task->fresh()->status);
    }

    public function test_failure_to_create_stop_rolls_back_start_and_task_status(): void
    {
        $task = $this->createTask();
        TaskTimeLog::creating(function (TaskTimeLog $log) {
            if ($log->status === TaskTimeLogStatus::STOP) {
                throw new \RuntimeException('Cannot create stop log');
            }
        });
        $this->withoutExceptionHandling();

        try {
            $this->putJson(self::ENDPOINT, [
                'taskId' => $task->id,
                'totalTime' => '01:01:01',
            ]);
            $this->fail('Expected stop creation to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cannot create stop log', $exception->getMessage());
        } finally {
            TaskTimeLog::clearBootedModels();
        }

        $this->assertSame(0, $task->timeLogs()->count());
        $this->assertSame(TaskStatus::TO_WORK, $task->fresh()->status);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_existing_stopped_log_is_updated_and_its_task_is_closed(): void
    {
        $task = $this->createTask();
        $log = $this->createLog($task, TaskTimeLogStatus::STOP);

        $this->putJson(self::ENDPOINT, [
            'taskId' => $task->id,
            'taskTimeLogId' => $log->id,
            'totalTime' => '02:03:04',
            'comment' => 'Corrected time',
        ])->assertOk();

        $this->assertSame('02:03:04', $log->fresh()->total_time);
        $this->assertSame('Corrected time', $log->fresh()->comment);
        $this->assertSame(1, $task->timeLogs()->count());
        $this->assertSame(TaskStatus::DONE, $task->fresh()->status);
    }

    public function test_existing_callers_can_still_send_only_the_log_id(): void
    {
        $task = $this->createTask();
        $log = $this->createLog($task, TaskTimeLogStatus::STOP);

        $this->putJson(self::ENDPOINT, [
            'taskTimeLogId' => $log->id,
            'totalTime' => '01:01:01',
        ])->assertOk();

        $this->assertSame('01:01:01', $log->fresh()->total_time);
        $this->assertSame(TaskStatus::DONE, $task->fresh()->status);
    }

    public function test_running_and_paused_logs_keep_the_existing_rejection_behavior(): void
    {
        foreach ([TaskTimeLogStatus::START, TaskTimeLogStatus::PAUSE] as $status) {
            $task = $this->createTask();
            $log = $this->createLog($task, $status);

            foreach ([$log->id, ''] as $logId) {
                $this->putJson(self::ENDPOINT, [
                    'taskId' => $task->id,
                    'taskTimeLogId' => $logId,
                    'totalTime' => '01:01:01',
                ])->assertOk()->assertJson(['message' => "you can't change this task time log"]);
            }

            $this->assertSame('00:10:00', $log->fresh()->total_time);
            $this->assertSame(TaskStatus::TO_WORK, $task->fresh()->status);
            $this->assertSame(1, $task->timeLogs()->count());
            $this->assertSame(0, DB::transactionLevel());
        }
    }

    public function test_a_log_from_another_task_cannot_be_changed(): void
    {
        $task = $this->createTask();
        $otherTask = $this->createTask();
        $log = $this->createLog($otherTask, TaskTimeLogStatus::STOP);

        $this->putJson(self::ENDPOINT, [
            'taskId' => $task->id,
            'taskTimeLogId' => $log->id,
            'totalTime' => '01:01:01',
        ])->assertNotFound();

        $this->assertSame('00:10:00', $log->fresh()->total_time);
        $this->assertSame(TaskStatus::TO_WORK, $task->fresh()->status);
        $this->assertSame(TaskStatus::TO_WORK, $otherTask->fresh()->status);
        $this->assertSame(0, $task->timeLogs()->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_unknown_ids_do_not_create_logs(): void
    {
        $task = $this->createTask();

        foreach ([['taskId' => 999], ['taskId' => $task->id, 'taskTimeLogId' => 999]] as $ids) {
            $this->putJson(self::ENDPOINT, $ids + ['totalTime' => '01:01:01'])->assertNotFound();
        }

        $this->assertSame(0, TaskTimeLog::count());
        $this->assertSame(TaskStatus::TO_WORK, $task->fresh()->status);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_invalid_durations_are_rejected_without_changing_the_task(): void
    {
        $task = $this->createTask();

        foreach (['01:60:00', '01:00:60', '-01:00:00', '10000:00:00', 'invalid', ''] as $time) {
            $this->putJson(self::ENDPOINT, [
                'taskId' => $task->id,
                'totalTime' => $time,
            ])->assertUnprocessable()->assertJsonValidationErrors('totalTime');
        }

        $this->putJson(self::ENDPOINT, ['totalTime' => '01:01:01'])
            ->assertUnprocessable()->assertJsonValidationErrors('taskId');
        $this->assertSame(0, $task->timeLogs()->count());
        $this->assertSame(TaskStatus::TO_WORK, $task->fresh()->status);
    }

    private function createTask(): Task
    {
        return Task::create(['title' => 'Manual time test', 'user_id' => 2, 'status' => TaskStatus::TO_WORK->value]);
    }

    private function createLog(Task $task, TaskTimeLogStatus $status): TaskTimeLog
    {
        return $task->timeLogs()->create([
            'user_id' => $task->user_id,
            'type' => TaskTimeLogType::TIME_LOG->value,
            'status' => $status->value,
            'total_time' => '00:10:00',
        ]);
    }
}
