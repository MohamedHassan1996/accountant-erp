<?php

namespace App\Services\Task;

use App\Enums\Task\TaskStatus;
use App\Filters\Task\FilterTask;
use App\Filters\Task\FilterTaskDateBetween;
use App\Filters\Task\FilterTaskStartEndDate;
use App\Models\Task\Task;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaskService{

    public function allTasks(){

        $tasks = QueryBuilder::for(Task::class)
        ->allowedFilters([
            AllowedFilter::custom('search', new FilterTask()), // Add a custom search filter
            AllowedFilter::exact('userId', 'user_id'),
            AllowedFilter::exact('status', 'status'),
            AllowedFilter::exact('serviceCategoryId', 'service_category_id'),
            AllowedFilter::exact('clientId', 'client_id'),
            AllowedFilter::custom('dateBetween', new FilterTaskDateBetween()),
            AllowedFilter::custom('startEndDate', new FilterTaskStartEndDate()),
        ])
        ->get();
        return $tasks;

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
            'end_date' => $taskData['endDate']??null
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
