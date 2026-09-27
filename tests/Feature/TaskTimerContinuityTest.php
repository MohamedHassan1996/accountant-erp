<?php

namespace Tests\Feature;

use App\Enums\Task\TaskStatus;
use App\Enums\Task\TaskTimeLogStatus;
use App\Models\Task\Task;
use App\Models\Task\TaskTimeLog;
use App\Services\Task\TaskService;
use App\Services\Task\ExportTaskService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TaskTimeLogTestCase;

class TaskTimerContinuityTest extends TaskTimeLogTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-27 10:00:00'));
    }

    public function test_pause_resume_and_stop_preserve_server_time_even_when_frontend_sends_zero(): void
    {
        $task = $this->task();
        $this->event($task, 0);
        $this->travel(1353)->seconds();
        $this->event($task, 1);
        $this->assertSame('00:22:33', $task->fresh()->current_time);
        $this->travel(300)->seconds();
        $this->assertSame('00:22:33', $task->fresh()->current_time);
        $this->event($task, 0);
        $this->assertSame('00:22:33', $task->fresh()->current_time);
        $this->travel(120)->seconds();
        $this->event($task, 2);
        $this->travel(60)->seconds();

        $this->getJson('/api/v1/tasks/edit?taskId='.$task->id)->assertOk()
            ->assertJsonPath('data.currentTime', '00:24:33')
            ->assertJsonPath('data.status', TaskStatus::DONE->value)
            ->assertJsonPath('data.timeLogStatus', TaskTimeLogStatus::STOP->value);
        $this->assertSame('00:24:33', app(TaskService::class)->allTasks()['totalTime']);
        $this->assertSame([0, 1, 0, 2], $task->timeLogs()->orderBy('id')->get()
            ->map(fn ($log) => $log->status->value)->all());
    }

    public function test_repeated_start_pause_and_stop_requests_do_not_reset_time_or_duplicate_events(): void
    {
        $task = $this->task();
        $start = $this->event($task, 0);
        $this->travel(30)->seconds();
        $this->assertSame($start, $this->event($task, 0));
        $this->assertSame('00:00:30', $task->fresh()->current_time);
        $pause = $this->event($task, 1);
        $this->travel(300)->seconds();
        $this->assertSame($pause, $this->event($task, 1));
        $stop = $this->event($task, 2);
        $this->assertSame($stop, $this->event($task, 2));
        $this->assertSame(3, $task->timeLogs()->count());
        $this->assertSame('00:00:30', $task->fresh()->total_hours);
    }

    public function test_only_starting_a_ticket_pauses_the_other_running_ticket(): void
    {
        $first = $this->task();
        $second = $this->task();
        $this->event($first, 0);
        $this->travel(60)->seconds();
        $this->event($second, 0);
        $this->assertSame(TaskTimeLogStatus::PAUSE, $first->fresh()->latestTimeLog->status);
        $this->assertSame('00:01:00', $first->fresh()->current_time);
        $this->travel(60)->seconds();
        $this->event($first, 1);
        $this->event($first, 2);
        $this->assertSame(TaskTimeLogStatus::START, $second->fresh()->latestTimeLog->status);
        $this->assertSame('00:01:00', $second->fresh()->current_time);
        $this->assertSame(1, $second->timeLogs()->count());
    }

    public function test_same_second_events_have_one_consistent_latest_record_and_total(): void
    {
        $task = $this->task();
        $this->event($task, 0, '00:10:00');
        $this->event($task, 1);
        $lastId = $this->event($task, 0);

        $this->assertSame('00:10:00', $task->fresh()->total_hours);
        $this->assertSame(0, $task->fresh()->time_log_status);
        $this->assertSame($lastId, $task->fresh()->latestTimeLog->id);
        $this->getJson('/api/v1/tasks/edit?taskId='.$task->id)->assertOk()
            ->assertJsonPath('data.latestTimeLogId', $lastId)
            ->assertJsonPath('data.timeLogStatus', 0);
        $this->assertSame('00:10:00', app(TaskService::class)->allTasks()['totalTime']);
        $this->assertSame('0:10:00', app(ExportTaskService::class)->allTasks()['totalTime']);
    }

    public function test_deleted_logs_do_not_override_the_current_total(): void
    {
        $task = $this->task();
        $this->event($task, 0, '00:10:00');
        $this->event($task, 1);
        $deleted = $task->timeLogs()->create(['user_id' => 2, 'type' => 0, 'status' => 2, 'total_time' => '99:00:00']);
        $deleted->delete();

        $this->assertSame('00:10:00', $task->fresh()->current_time);
        $this->assertSame('00:10:00', app(TaskService::class)->allTasks()['totalTime']);
        $this->assertSame('0:10:00', app(ExportTaskService::class)->allTasks()['totalTime']);
    }

    public function test_manual_completion_can_set_ten_hours_to_eight_twelve_or_zero(): void
    {
        foreach (['08:00:00', '12:00:00', '00:00:00'] as $target) {
            $task = $this->task();
            $this->event($task, 0);
            $this->travel(10)->hours();
            $this->assertSame('10:00:00', $task->fresh()->current_time);
            $this->postJson('/api/v1/task-time-logs/complete-ticket', [
                'ticketId' => $task->id, 'note' => 'Manual SET', 'totalTime' => $target,
            ])->assertOk()->assertJsonPath('data.totalTime', $target);
            $this->travel(5)->minutes();
            $this->assertSame($target, $task->fresh()->current_time);
            $this->assertSame(TaskStatus::DONE, $task->fresh()->status);
            $this->assertSame(2, $task->timeLogs()->count());
            $this->assertSame('Manual SET', $task->timeLogs()->latest('id')->first()->comment);
        }
    }

    public function test_change_time_can_set_a_stopped_total_down_and_up_and_resume_uses_the_new_total(): void
    {
        $task = $this->task();
        $this->event($task, 0, '10:00:00');
        $stopId = $this->event($task, 2);
        foreach (['08:00:00', '12:00:00'] as $target) {
            $this->putJson('/api/v1/task-time-logs/change-time', [
                'taskId' => $task->id, 'taskTimeLogId' => $stopId,
                'totalTime' => $target, 'comment' => 'Correction',
            ])->assertOk();
            $this->assertSame($target, $task->fresh()->current_time);
        }
        $this->event($task, 0);
        $this->travel(60)->seconds();
        $this->assertSame('12:01:00', $task->fresh()->current_time);
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->fresh()->status);
    }

    public function test_resuming_ignores_a_stale_or_inflated_frontend_counter(): void
    {
        $task = $this->task();
        $this->event($task, 0);
        $this->travel(120)->seconds();
        $this->event($task, 1, '99:00:00');
        $this->travel(300)->seconds();
        $this->event($task, 0, '99:00:00');
        $this->assertSame('00:02:00', $task->fresh()->current_time);
    }

    public function test_active_task_endpoints_use_the_same_counter_with_the_existing_keys(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('ragione_sociale');
        });
        DB::table('clients')->insert(['id' => 1, 'ragione_sociale' => 'Test client']);
        $task = $this->task();
        $task->update(['user_id' => 1, 'client_id' => 1, 'status' => TaskStatus::IN_PROGRESS->value]);
        $this->getJson('/api/v1/user-active-tasks')->assertOk()
            ->assertJsonPath('0.time', '00:00:00')->assertJsonPath('0.timerStatus', 0);
        $startId = $this->event($task, 0);
        $this->travel(300)->seconds();
        $this->getJson('/api/v1/user-active-tasks')->assertOk()
            ->assertJsonPath('0.totalTime', 300)->assertJsonPath('0.time', '00:05:00')
            ->assertJsonPath('0.timerStatus', 1)->assertJsonPath('0.timeLogId', $startId);

        $this->putJson('/api/v1/user-active-tasks/update', [
            'taskTimeLogId' => $startId, 'endAt' => now()->toDateTimeString(), 'taskStatus' => 1,
        ])->assertOk();
        $this->travel(300)->seconds();
        $this->getJson('/api/v1/user-active-tasks')->assertOk()
            ->assertJsonPath('0.totalTime', 300)->assertJsonPath('0.time', '00:05:00')
            ->assertJsonPath('0.timerStatus', 2);

        $this->putJson('/api/v1/user-active-tasks/update', [
            'taskTimeLogId' => $startId, 'endAt' => null, 'taskStatus' => 1,
        ])->assertOk();
        $this->travel(60)->seconds();
        $this->putJson('/api/v1/user-active-tasks/update', [
            'taskTimeLogId' => $startId, 'endAt' => now()->toDateTimeString(), 'taskStatus' => 2,
        ])->assertOk();
        $this->getJson('/api/v1/user-active-tasks')->assertOk()->assertExactJson([]);
        $this->assertSame('00:06:00', $task->fresh()->current_time);
        $this->assertSame(TaskStatus::DONE, $task->fresh()->status);
    }

    public function test_failed_timer_switch_rolls_back_the_automatic_pause(): void
    {
        $first = $this->task();
        $second = $this->task();
        $startId = $this->event($first, 0);
        $this->travel(60)->seconds();
        TaskTimeLog::creating(function (TaskTimeLog $log) use ($second) {
            if ($log->task_id === $second->id) {
                throw new \RuntimeException('Cannot start timer');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->event($second, 0);
            $this->fail('Expected timer switch to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cannot start timer', $exception->getMessage());
        } finally {
            TaskTimeLog::clearBootedModels();
        }
        $this->assertSame(1, $first->timeLogs()->count());
        $this->assertSame(0, $second->timeLogs()->count());
        $this->assertNull(TaskTimeLog::findOrFail($startId)->end_at);
        $this->assertSame(TaskStatus::TO_WORK, $second->fresh()->status);
        $this->assertSame(0, DB::transactionLevel());
    }

    private function task(): Task
    {
        return Task::create(['title' => 'Timer continuity', 'user_id' => 2, 'status' => 0]);
    }

    private function event(Task $task, int $status, string $time = '00:00:00'): int
    {
        return $this->postJson('/api/v1/task-time-logs/create', [
            'taskId' => $task->id, 'userId' => $task->user_id, 'type' => 0,
            'status' => $status, 'currentTime' => $time,
        ])->assertOk()->json('data.taskTimeLogId');
    }
}
