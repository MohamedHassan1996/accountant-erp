<?php

namespace Tests\Feature;

use App\Enums\Task\TaskStatus;
use App\Enums\Task\TaskTimeLogStatus;
use App\Enums\Task\TaskTimeLogType;
use App\Models\Task\Task;
use App\Models\Task\TaskTimeLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Models\Permission;
use Tests\Support\TaskTimeLogTestCase;

class CompleteTicketTimeTest extends TaskTimeLogTestCase
{
    private const ENDPOINT = '/api/v1/task-time-logs/complete-ticket';

    public function test_ticket_without_logs_gets_start_then_stop_and_retries_reuse_stop(): void
    {
        $this->freezeTime();
        $task = $this->createTask();
        $payload = ['ticketId' => $task->id, 'note' => 'Finished manually', 'totalTime' => '01:01:01'];

        $response = $this->postJson(self::ENDPOINT, $payload)->assertOk()
            ->assertJsonPath('data.ticketId', $task->id)
            ->assertJsonPath('data.status', TaskStatus::DONE->value)
            ->assertJsonPath('data.timeLogStatus', TaskTimeLogStatus::STOP->value)
            ->assertJsonPath('data.totalTime', '01:01:01')
            ->assertJsonPath('data.note', 'Finished manually');

        $logs = $task->timeLogs()->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(TaskTimeLogStatus::START, $logs[0]->status);
        $this->assertSame('00:00:00', $logs[0]->total_time);
        $this->assertSame(TaskTimeLogStatus::STOP, $logs[1]->status);
        $this->assertSame('Finished manually', $logs[1]->comment);
        $this->assertSame(2, $logs[1]->user_id);
        $this->assertSame(1, $logs[1]->created_by);
        $this->assertSame($logs[1]->id, $response->json('data.taskTimeLogId'));
        $this->assertSame(TaskStatus::DONE, $task->fresh()->status);

        $this->getJson('/api/v1/tasks/edit?taskId='.$task->id)->assertOk()
            ->assertJsonPath('data.note', 'Finished manually')
            ->assertJsonPath('data.latestTimeLogId', $logs[1]->id);
        $this->getJson('/api/v1/task-time-logs/edit?taskTimeLogId='.$logs[1]->id)->assertOk()
            ->assertJsonPath('data.note', 'Finished manually')
            ->assertJsonPath('data.comment', 'Finished manually');
        $history = $this->getJson('/api/v1/task-time-logs?taskId='.$task->id)->assertOk();
        $stopEntry = collect($history->json('data'))->firstWhere('taskTimeLogId', $logs[1]->id);
        $this->assertSame('Finished manually', $stopEntry['note']);
        $this->assertSame('Finished manually', $stopEntry['comment']);

        $this->postJson(self::ENDPOINT, $payload)->assertOk()
            ->assertJsonPath('data.taskTimeLogId', $logs[1]->id);
        $this->assertSame(2, $task->timeLogs()->count());
        $this->assertSame('01:01:01', $task->fresh()->total_hours);
    }

    public function test_running_ticket_closes_the_existing_session_without_an_extra_start(): void
    {
        $this->freezeTime();
        $task = $this->createTask(TaskStatus::IN_PROGRESS);
        $start = $this->createLog($task, TaskTimeLogStatus::START);
        $start->created_at = now()->subMinutes(5);
        $start->save();

        $this->postJson(self::ENDPOINT, [
            'ticketId' => $task->id,
            'note' => 'Corrected final duration',
            'totalTime' => '00:02:00',
        ])->assertOk()->assertJsonPath('data.totalTime', '00:02:00');

        $this->assertSame(2, $task->timeLogs()->count());
        $this->assertSame(1, $task->timeLogs()->where('status', TaskTimeLogStatus::START->value)->count());
        $this->assertSame(TaskTimeLogStatus::START, $start->fresh()->status);
        $this->assertSame('00:10:00', $start->fresh()->total_time);
        $this->assertTrue($start->fresh()->end_at->equalTo(now()->startOfSecond()));
        $this->assertSame(TaskStatus::DONE, $task->fresh()->status);

        $this->travel(10)->minutes();
        $this->assertSame('00:02:00', $task->fresh()->current_time);
        $this->assertSame('00:02:00', $task->fresh()->total_hours);
        $this->assertSame(TaskTimeLogStatus::STOP->value, $task->fresh()->time_log_status);
    }

    public function test_paused_ticket_only_gets_a_stop_and_other_tickets_are_unchanged(): void
    {
        $this->freezeTime();
        $task = $this->createTask(TaskStatus::IN_PROGRESS);
        $start = $this->createLog($task, TaskTimeLogStatus::START);
        $start->created_at = now()->subMinutes(10);
        $start->end_at = now()->subMinutes(5);
        $start->save();
        $pause = $this->createLog($task, TaskTimeLogStatus::PAUSE);
        $otherTask = $this->createTask(TaskStatus::IN_PROGRESS);
        $otherStart = $this->createLog($otherTask, TaskTimeLogStatus::START);

        $this->postJson(self::ENDPOINT, [
            'ticketId' => $task->id,
            'note' => '',
            'totalTime' => '125:30:45',
        ])->assertOk()->assertJsonPath('data.note', '');

        $this->assertSame(3, $task->timeLogs()->count());
        $this->assertSame(TaskTimeLogStatus::PAUSE, $pause->fresh()->status);
        $this->assertTrue($start->fresh()->end_at->equalTo(now()->subMinutes(5)->startOfSecond()));
        $this->assertSame('125:30:45', $task->fresh()->total_hours);
        $this->assertSame(TaskStatus::DONE, $task->fresh()->status);
        $this->assertSame(TaskStatus::IN_PROGRESS, $otherTask->fresh()->status);
        $this->assertSame(1, $otherTask->timeLogs()->count());
        $this->assertNull($otherStart->fresh()->end_at);
    }

    public function test_already_stopped_ticket_updates_the_existing_stop(): void
    {
        $task = $this->createTask(TaskStatus::DONE);
        $stop = $this->createLog($task, TaskTimeLogStatus::STOP);

        $this->postJson(self::ENDPOINT, [
            'ticketId' => $task->id,
            'note' => 'Updated note',
            'totalTime' => '02:03:04',
        ])->assertOk()->assertJsonPath('data.taskTimeLogId', $stop->id);

        $this->assertSame(1, $task->timeLogs()->count());
        $this->assertSame('02:03:04', $stop->fresh()->total_time);
        $this->assertSame('Updated note', $stop->fresh()->comment);
        $this->assertSame(TaskStatus::DONE, $task->fresh()->status);
    }

    public function test_immediate_completion_with_zero_time_keeps_stop_as_the_latest_event(): void
    {
        $this->freezeTime();
        $task = $this->createTask(TaskStatus::IN_PROGRESS);
        $this->createLog($task, TaskTimeLogStatus::START);

        $this->postJson(self::ENDPOINT, ['ticketId' => $task->id, 'totalTime' => '00:00:00'])
            ->assertOk()->assertJsonPath('data.note', '');

        $this->travel(5)->seconds();
        $this->assertSame('00:00:00', $task->fresh()->total_hours);
        $this->assertSame(TaskTimeLogStatus::STOP->value, $task->fresh()->time_log_status);
        $this->assertSame(TaskTimeLogStatus::STOP, $task->fresh()->latestTimeLog->status);
    }

    public function test_invalid_payloads_and_missing_or_deleted_tickets_cannot_create_logs(): void
    {
        $task = $this->createTask();
        foreach ([
            ['totalTime' => '01:60:00'],
            ['totalTime' => ''],
            ['totalTime' => '10000:00:00'],
            ['ticketId' => ''],
            ['ticketId' => 0],
            ['note' => ['invalid']],
        ] as $invalid) {
            $this->postJson(self::ENDPOINT, array_merge([
                'ticketId' => $task->id, 'totalTime' => '01:01:01',
            ], $invalid))->assertUnprocessable();
        }
        $this->postJson(self::ENDPOINT, ['ticketId' => 999, 'totalTime' => '01:01:01'])->assertNotFound();
        $task->delete();
        $this->postJson(self::ENDPOINT, ['ticketId' => $task->id, 'totalTime' => '01:01:01'])->assertNotFound();

        $this->assertSame(0, TaskTimeLog::count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_stop_failure_rolls_back_closing_the_running_session(): void
    {
        $task = $this->createTask(TaskStatus::IN_PROGRESS);
        $start = $this->createLog($task, TaskTimeLogStatus::START);
        TaskTimeLog::creating(function (TaskTimeLog $log) {
            if ($log->status === TaskTimeLogStatus::STOP) {
                throw new \RuntimeException('Cannot create stop log');
            }
        });
        $this->withoutExceptionHandling();

        try {
            $this->postJson(self::ENDPOINT, ['ticketId' => $task->id, 'totalTime' => '01:01:01']);
            $this->fail('Expected stop creation to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cannot create stop log', $exception->getMessage());
        } finally {
            TaskTimeLog::clearBootedModels();
        }

        $this->assertSame(1, $task->timeLogs()->count());
        $this->assertNull($start->fresh()->end_at);
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->fresh()->status);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_authentication_is_required(): void
    {
        $task = $this->createTask();
        Auth::forgetGuards();

        $this->postJson(self::ENDPOINT, ['ticketId' => $task->id, 'totalTime' => '01:01:01'])->assertUnauthorized();

        $this->assertSame(0, $task->timeLogs()->count());
        $this->assertSame(TaskStatus::TO_WORK, $task->fresh()->status);
    }

    public function test_completion_requires_the_dedicated_permission_instead_of_change_time(): void
    {
        (require database_path('migrations/2024_12_19_044444_create_permission_tables.php'))->up();
        $this->withMiddleware(PermissionMiddleware::class);
        $user = User::findOrFail(1);
        $this->actingAs($user, 'api');
        $task = $this->createTask();
        $payload = ['ticketId' => $task->id, 'totalTime' => '01:01:01'];
        Permission::create(['name' => 'change_task_time_log', 'guard_name' => 'api']);
        Permission::create(['name' => 'complete-ticket-with-time', 'guard_name' => 'api']);

        $this->postJson(self::ENDPOINT, $payload)->assertForbidden();
        $user->givePermissionTo('change_task_time_log');
        $this->postJson(self::ENDPOINT, $payload)->assertForbidden();
        $this->assertSame(0, $task->timeLogs()->count());
        $this->assertSame(TaskStatus::TO_WORK, $task->fresh()->status);

        $user->syncPermissions(['complete-ticket-with-time']);
        $this->postJson(self::ENDPOINT, $payload)->assertOk()
            ->assertJsonPath('data.status', TaskStatus::DONE->value);
        $this->assertSame(2, $task->timeLogs()->count());
    }

    private function createTask(TaskStatus $status = TaskStatus::TO_WORK): Task
    {
        return Task::create(['title' => 'Complete ticket test', 'user_id' => 2, 'status' => $status->value]);
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
