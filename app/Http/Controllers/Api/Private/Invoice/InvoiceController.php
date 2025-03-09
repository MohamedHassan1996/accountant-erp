<?php

namespace App\Http\Controllers\Api\Private\Invoice;

use App\Models\Task\Task;
use Illuminate\Http\Request;
use App\Enums\Task\TaskStatus;
use App\Models\Invoice\Invoice;
use App\Utils\PaginateCollection;
use App\Services\Task\TaskService;
use Illuminate\Support\Facades\DB;
use App\Enums\Client\ClientCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\Task\TaskResource;
use App\Models\Client\ClientServiceDiscount;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Enums\Client\ClientServiceDiscountStatus;
use App\Enums\Client\ServiceDiscountCategory;
use App\Http\Resources\Invoice\AllInvoiceCollection;
use App\Enums\ServiceCategory\ServiceCategoryAddToInvoiceStatus;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->middleware('auth:api');
        $this->middleware('permission:all_invoices', ['only' => ['index']]);
        $this->middleware('permission:create_invoice', ['only' => ['create']]);
        // $this->middleware('permission:edit_invoice', ['only' => ['edit']]);
        // $this->middleware('permission:update_invoice', ['only' => ['update']]);
        // $this->middleware('permission:delete_invoice', ['only' => ['delete']]);
        $this->taskService = $taskService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->filter ?? null;

        $allInvoices = DB::table('tasks')
            ->leftJoin('invoices', 'invoices.id', '=', 'tasks.invoice_id')
            ->leftJoin('clients', 'tasks.client_id', '=', 'clients.id')
            ->leftJoin('service_categories', 'tasks.service_category_id', '=', 'service_categories.id')
            ->when(isset($filters['clientId']), function ($query) use ($filters) {
                return $query->where('tasks.client_id', $filters['clientId']);
            })
            ->when(isset($filters['unassigned']), function ($query) use ($filters) {
                return $query->where('tasks.invoice_id', $filters['unassigned'] == 1 ? '=' : '!=', null);
            })
            ->where('tasks.status', TaskStatus::DONE->value)
            ->whereNull('invoices.deleted_at')
            ->whereNull('tasks.deleted_at')
            ->where('tasks.is_new', '!=', 0)
            ->select([
                'invoices.id as invoiceId',
                'clients.id as clientId',
                'clients.ragione_sociale as clientName',
                'clients.addable_to_bulk_invoice as clientAddableToBulkInvoice',
                'invoices.number as invoiceNumber',
                'invoices.discount_type as invoiceDiscountType',
                'invoices.discount_amount as invoiceDiscountAmount',
                'tasks.id as taskId',
                'tasks.status as taskStatus',
                'tasks.title as taskTitle',
                'tasks.price as taskPrice',
                'tasks.created_at as taskCreatedAt',
                'tasks.price_after_discount as taskPriceAfterDiscount',
                'tasks.number as taskNumber',
                'tasks.invoice_id as invoiceId',
                'service_categories.id as serviceCategoryId',
                'service_categories.name as serviceCategoryName',
                'service_categories.price as serviceCategoryPrice',
                'service_categories.add_to_invoice as serviceCategoryAddToInvoice'
            ])
            ->get();

        // Format the data
        $formattedData = [];
        foreach ($allInvoices as $invoice) {
            $key = $invoice->invoiceId != null
                ? $invoice->invoiceId
                : "unassigned##{$invoice->clientId}";

            if (!in_array($key, array_column($formattedData, 'key'))) {
                $formattedData[] = [
                    'key' => $key,
                    'invoiceId' => $invoice->invoiceId??"",
                    'invoiceNumber' => $invoice->invoiceNumber ?? "",
                    'clientId' => $invoice->clientId ?? "",
                    'clientName' => $invoice->clientName ?? "",
                    'clientAddableToBulkInvoice' => $invoice->clientAddableToBulkInvoice ?? "",
                    'tasks' => [],
                    'totalPrice' => 0,
                    'totalPriceAfterDiscount' => 0
                ];
            }

            $search = array_search($key, array_column($formattedData, 'key'));

            $servicePrice = $invoice->serviceCategoryAddToInvoice == ServiceCategoryAddToInvoiceStatus::ADD->value ? $invoice->serviceCategoryPrice : 0;

            $clientDiscount = ClientServiceDiscount::where('client_id', $invoice->clientId)
                ->where('service_category_id', $invoice->serviceCategoryId)
                ->where('is_active', ClientServiceDiscountStatus::ACTIVE->value)
                ->first();

            $formattedData[$search]['totalPrice'] += $servicePrice;
            $formattedData[$search]['totalPriceAfterDiscount'] += $servicePrice;

            $servicePriceAfterDiscount = $servicePrice;

            if ($clientDiscount && $servicePrice > 0) {
                $servicePriceAfterDiscount = $servicePrice - ($servicePrice * ($clientDiscount->discount / 100));
                $formattedData[$search]['totalPriceAfterDiscount'] += $servicePriceAfterDiscount;
            }

            $formattedData[$search]['tasks'][] = [
                'taskId' => $invoice->taskId,
                'taskTitle' => $invoice->taskTitle,
                'taskNumber' => $invoice->taskNumber,
                'serviceCategoryName' => $invoice->serviceCategoryName,
                'taskStatus' => $invoice->taskStatus,
                'price' =>$invoice->taskPrice ?? $servicePrice,
                'priceAfterDiscount' =>$invoice->taskPriceAfterDiscount??$servicePriceAfterDiscount,
                'taskCreatedAt' => Carbon::parse($invoice->taskCreatedAt)->format('d/m/Y H:i')
            ];
        }

        // Paginate the formatted data
        $pageSize = $request->pageSize ?? 10;
        $paginatedData = PaginateCollection::paginate(collect($formattedData), $pageSize);

        return response()->json(new AllInvoiceCollection($paginatedData), 200);
    }


    /**
     * Show the form for creating a new resource.
     */

    public function create(Request $createTaskRequest)
    {
        try {
            DB::beginTransaction();

            /*

            invoices = [
                {
                    "clientId": 1,
                    "endAt": "2023-01-01",
                    "paymentTypeId": 1,
                    "taskIds": [1, 2, 3]
                }
            ]
            */

            foreach ($createTaskRequest->invoices as  $invoiceData) {
                $endDate = Carbon::parse($invoiceData['endAt']);

                if ($endDate->format('d-m') === '31-08' || $endDate->format('d-m') === '31-12') {
                    $endDate->addDays(10);
                }

                $invoice = Invoice::create([
                    'client_id' => $invoiceData['clientId'],
                    'end_at' => $endDate,
                    'payment_type_id' => $invoiceData['paymentTypeId'],
                    'discount_type' => $invoiceData['discountType'],
                    'discount_amount' => $invoiceData['discountAmount']
                ]);
                $invoiceTasks = $invoiceData['taskIds'];
                $clientDiscount=  ClientServiceDiscount::where('client_id', $invoiceData['clientId'])->first();
                    foreach ($invoiceTasks as  $taskId) {
                        if (!$task = Task::find($taskId)) {
                            continue;
                        }

                        $servicePrice = $task->serviceCategory->price ?? 0;
                        $serviceDiscount = $clientDiscount->discount ?? 0;
                        $priceAfterDiscount = $servicePrice;

                        if (!empty($clientDiscount?->category)) {
                            $priceAfterDiscount = $clientDiscount->category == ServiceDiscountCategory::TAX->value
                                ? $servicePrice * (1 + $serviceDiscount / 100)
                                : $servicePrice * (1 - $serviceDiscount / 100);
                        }

                        $task->update([
                            "price" => $servicePrice,
                            "price_after_discount" => $priceAfterDiscount,
                            "invoice_id" => $invoice->id,
                        ]);
                    }
            }


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
