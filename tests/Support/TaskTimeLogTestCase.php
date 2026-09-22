<?php

namespace Tests\Support;

use App\Enums\Task\TaskStatus;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Tests\TestCase;

abstract class TaskTimeLogTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Keep these endpoint tests isolated from the configured application database.
        config([
            'database.default' => 'change_time_testing',
            'database.connections.change_time_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('service_category_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('connection_type_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('quantity')->nullable();
            $table->string('number')->nullable();
            $table->boolean('is_new')->default(true);
            $table->tinyInteger('status')->default(TaskStatus::TO_WORK->value);
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        (require database_path('migrations/2024_12_20_232746_create_task_time_logs_table.php'))->up();

        DB::table('users')->insert([['id' => 1], ['id' => 2]]);
        $this->actingAs((new User)->forceFill(['id' => 1]), 'api');
        $this->withoutMiddleware(PermissionMiddleware::class);
    }

}
