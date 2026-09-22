<?php

namespace Tests\Feature;

use App\Enums\Task\TaskStatus;
use App\Enums\Task\TaskTimeLogStatus;
use App\Enums\Task\TaskTimeLogType;
use App\Models\Task\Task;
use App\Models\Task\TaskTimeLog;
use Illuminate\Support\Facades\DB;
use Tests\Support\TaskTimeLogTestCase;

class CreateTaskWithTimeTest extends TaskTimeLogTestCase
{
    private const ENDPOINT = '/api/v1/tasks/create';

    public function test_creating_with_time_and_note_creates_start_then_stop_and_returns_done(): void
    {
        $this->freezeTime();
        $response = $this->postJson(self::ENDPOINT, $this->payload([
            'totalTime' => '01:01:01',
            'note' => 'Completed during the client meeting',
        ]))->assertOk()
            ->assertJsonPath('data.status', TaskStatus::DONE->value)
            ->assertJsonPath('data.currentTime', '01:01:01')
            ->assertJsonPath('data.timeLogStatus', TaskTimeLogStatus::STOP->value);

        $task = Task::findOrFail($response->json('data.taskId'));
        $logs = $task->timeLogs()->orderBy('id')->get();
        $this->assertCount(2, $logs);
        [$startLog, $stopLog] = $logs->all();
        $this->assertSame(TaskTimeLogStatus::START, $startLog->status);
        $this->assertSame('00:00:00', $startLog->total_time);
        $this->assertNull($startLog->comment);
        $this->assertSame(TaskTimeLogStatus::STOP, $stopLog->status);
        $this->assertSame('01:01:01', $stopLog->total_time);
        $this->assertSame('Completed during the client meeting', $stopLog->comment);
        $this->assertSame(3661, $startLog->created_at->diffInSeconds($stopLog->created_at));
        foreach ($logs as $log) {
            $this->assertSame(TaskTimeLogType::TIME_LOG, $log->type);
            $this->assertSame(2, $log->user_id);
            $this->assertSame(1, $log->created_by);
        }
        $this->assertSame(TaskStatus::DONE, $task->status);
        $this->assertSame($stopLog->id, $response->json('data.latestTimeLogId'));
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_creation_without_time_preserves_the_requested_status_and_creates_no_logs(): void
    {
        foreach ([[], ['totalTime' => '', 'note' => ''], ['totalTime' => null, 'note' => null]] as $fields) {
            $this->postJson(self::ENDPOINT, $this->payload($fields))
                ->assertOk()
                ->assertJsonPath('data.status', TaskStatus::TO_WORK->value)
                ->assertJsonPath('data.currentTime', '00:00:00')
                ->assertJsonPath('data.latestTimeLogId', '');
        }

        $this->assertSame(3, Task::count());
        $this->assertSame(0, TaskTimeLog::count());
    }

    public function test_time_accepts_zero_and_more_than_24_hours_with_an_optional_note(): void
    {
        $this->freezeTime();
        foreach (['00:00:00', '125:30:45'] as $time) {
            $response = $this->postJson(self::ENDPOINT, $this->payload(['totalTime' => $time, 'note' => '']))
                ->assertOk()
                ->assertJsonPath('data.status', TaskStatus::DONE->value)
                ->assertJsonPath('data.currentTime', $time)
                ->assertJsonPath('data.timeLogStatus', TaskTimeLogStatus::STOP->value);

            $task = Task::findOrFail($response->json('data.taskId'));
            $this->assertSame(2, $task->timeLogs()->count());
            $this->assertNull($task->timeLogs()->latest()->first()->comment);
            $this->assertSame($time, $task->total_hours);
        }
    }

    public function test_invalid_time_or_note_is_rejected_before_creating_the_task(): void
    {
        foreach (['01:60:00', '01:00:60', '10000:00:00', 'invalid'] as $time) {
            $this->postJson(self::ENDPOINT, $this->payload(['totalTime' => $time]))
                ->assertStatus(401)->assertJsonStructure(['message' => ['totalTime']]);
        }
        $this->postJson(self::ENDPOINT, $this->payload(['note' => 'Needs a duration']))
            ->assertStatus(401)->assertJsonStructure(['message' => ['totalTime']]);
        $this->postJson(self::ENDPOINT, $this->payload(['totalTime' => '01:01:01', 'note' => ['invalid']]))
            ->assertStatus(401)->assertJsonStructure(['message' => ['note']]);

        $this->assertSame(0, Task::count());
        $this->assertSame(0, TaskTimeLog::count());
    }

    public function test_failure_to_create_stop_rolls_back_the_new_task_and_start_log(): void
    {
        TaskTimeLog::creating(function (TaskTimeLog $log) {
            if ($log->status === TaskTimeLogStatus::STOP) {
                throw new \RuntimeException('Cannot create stop log');
            }
        });
        $this->withoutExceptionHandling();

        try {
            $this->postJson(self::ENDPOINT, $this->payload(['totalTime' => '01:01:01', 'note' => 'Rollback test']));
            $this->fail('Expected stop creation to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cannot create stop log', $exception->getMessage());
        } finally {
            TaskTimeLog::clearBootedModels();
        }

        $this->assertSame(0, Task::count());
        $this->assertSame(0, TaskTimeLog::count());
        $this->assertSame(0, DB::transactionLevel());
    }

    private function payload(array $fields = []): array
    {
        return array_merge([
            'title' => 'Task with manual time',
            'description' => 'Task description',
            'status' => TaskStatus::TO_WORK->value,
            'clientId' => 1,
            'userId' => 2,
            'serviceCategoryId' => 1,
        ], $fields);
    }
}
