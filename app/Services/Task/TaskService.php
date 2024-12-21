<?php

namespace App\Services\Task;

use App\Enums\Task\TaskStatus;
use App\Filters\Task\FilterTask;
use App\Models\Task\Task;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaskService{

    public function allTaskes(array $filters){

        $tasks = QueryBuilder::for(Task::class)
        ->allowedFilters([
            //AllowedFilter::custom('search', new FilterTask()), // Add a custom search filter
            //AllowedFilter::exact('taskType', 'task_type'),
        ])
        ->when(
            $filters['clientId'] ?? null,
            fn ($query) => $query->where('client_id', $filters['clientId'])
        )
        ->get();
        return $tasks;

    }

    public function createTask(array $taskData){

        $task = Task::create([
            'title' => $taskData['title'],
            'client_id' => $taskData['clientId'],
            'user_id' => $taskData['userId'],
            'service_category_id' => $taskData['serviceCategoryId'],
            'invoice_id' => $taskData['invoiceId']??null,
            'status' => TaskStatus::from($taskData['type'])->value,

        ]);

        return $task;

    }

    public function editTask(string $taskId){
        $task = Task::find($taskId);

        return $task;

    }

    public function updateTask(array $taskData){

        $task = Task::find($taskData['taskId']);

        $task->fill([
            'title' => $taskData['title'],
            'client_id' => $taskData['clientId'],
            'user_id' => $taskData['userId'],
            'service_category_id' => $taskData['serviceCategoryId'],
            'invoice_id' => $taskData['invoiceId']??null,
            'status' => TaskStatus::from($taskData['type'])->value,
        ]);

        $task->save();

        return $task;

    }

    public function deleteTask(string $taskId){
        $task = Task::find($taskId);
        $task->delete();
    }

}
