<?php

namespace App\Http\Controllers\Api\Private\Invoice;

use App\Enums\Task\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\CreateTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\Task\AllTaskCollection;
use App\Http\Resources\Task\AllTaskResource;
use App\Http\Resources\Task\TaskResource;
use App\Models\Invoice\Invoice;
use App\Services\Task\TaskService;
use App\Utils\PaginateCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function PHPSTORM_META\map;

class InvoiceController extends Controller
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->middleware('auth:api');
        // $this->middleware('permission:all_tasks', ['only' => ['index']]);
        // $this->middleware('permission:create_task', ['only' => ['create']]);
        // $this->middleware('permission:edit_task', ['only' => ['edit']]);
        // $this->middleware('permission:update_task', ['only' => ['update']]);
        // $this->middleware('permission:delete_task', ['only' => ['delete']]);
        $this->taskService = $taskService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $allInvoices = DB::table('tasks')
            ->leftJoin('invoices', 'invoices.id', '=', 'tasks.invoice_id')
            ->leftJoin('clients', 'tasks.client_id', '=', 'clients.id')
            ->where('tasks.client_id', $request->clientId)
            ->where('tasks.status', TaskStatus::DONE->value)
            ->select([
                'invoices.id as invoiceId',
                'clients.id as clientId',
                'clients.ragione_sociale as clientName',
                'invoices.number as invoiceNumber',
                'tasks.id as taskId',
                'tasks.title as taskTitle',
                'tasks.invoice_id as invoiceId',
            ])
            ->get();
            $formattedData = [];
            foreach ($allInvoices as $index => $invoice) {
                if(!in_array($invoice->invoiceId != null? $invoice->invoiceId: "unassigned##{$invoice->clientId}", array_column($formattedData, 'key'))) {
                    $formattedData[] = [
                        'key' => $invoice->invoiceId != null? $invoice->invoiceId: "unassigned##{$invoice->clientId}",
                        'invoiceNumber' => $invoice->invoiceNumber??"",
                        'clientName' => $invoice->clientName??"",
                        'tasks' => []
                    ];
                }
                $search = array_search($invoice->invoiceId != null? $invoice->invoiceId: "unassigned##{$invoice->clientId}", array_column($formattedData, 'key'));
                $formattedData[$search]['tasks'][] = [
                    'taskId' => $invoice->taskId,
                    'taskTitle' => $invoice->taskTitle
                ];
            }
            return response()->json($formattedData);

    }

    /**
     * Show the form for creating a new resource.
     */

    public function create(Request $createTaskRequest)
    {

        try {
            DB::beginTransaction();

            $invoice = Invoice::create([
                'client_id' => $createTaskRequest->clientId,
            ]);

            $invoiceDetails = array_map(function ($taskId) use ($invoice) {
                return [
                    'invoice_id' => $invoice->id,
                    'task_id' => $taskId,
                ];
            }, $createTaskRequest->taskIds);

            $invoice->invoiceDetails()->createMany($invoiceDetails);


            DB::commit();

            return response()->json([
                'message' => __('messages.success.created')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }


    }

    /**
     * Show the form for editing the specified resource.
     */

    public function edit(Request $request)
    {
        $task  =  $this->taskService->editTask($request->taskId);

        return new TaskResource($task);


    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskRequest $updateTaskRequest)
    {

        try {
            DB::beginTransaction();
            $this->taskService->updateTask($updateTaskRequest->validated());
            DB::commit();
            return response()->json([
                 'message' => __('messages.success.updated')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }


    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request)
    {

        try {
            DB::beginTransaction();
            $this->taskService->deleteTask($request->taskId);
            DB::commit();
            return response()->json([
                'message' => __('messages.success.deleted')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }


    }

    public function changeStatus(Request $request)
    {

        try {
            DB::beginTransaction();
            $this->taskService->changeStatus($request->taskId, $request->status);
            DB::commit();
            return response()->json([
                'message' => __('messages.success.updated')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }


    }

}
