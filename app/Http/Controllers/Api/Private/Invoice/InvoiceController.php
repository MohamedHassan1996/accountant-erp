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
use App\Enums\Client\ClientServiceDiscountType;
use App\Enums\Client\ServiceDiscountCategory;
use App\Http\Resources\Invoice\AllInvoiceCollection;
use App\Enums\ServiceCategory\ServiceCategoryAddToInvoiceStatus;
use App\Models\Client\ClientAddress;
use App\Models\Client\ClientPayInstallment;
use App\Models\Client\ClientPayInstallmentSubData;
use App\Models\Invoice\InvoiceDetail;
use App\Models\ServiceCategory\ServiceCategory;
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


    public function index(Request $request)
    {
        $filters = $request->filter ?? null;

        if($filters['unassigned'] == 0){
            return $this->assignedInvoices($request);
        }

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
            ->where('tasks.is_new',  1)
            ->when(!empty($filters['unassigned']), function ($query) {
                $query->whereDate('tasks.created_at', '>', Carbon::parse('2026-01-04'));
            })->select([
                'invoices.id as invoiceId',
                'invoices.created_at as invoiceCreatedAt',
                'clients.id as clientId',
                'clients.total_tax as clientTotalTax',
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
                'service_categories.add_to_invoice as serviceCategoryAddToInvoice',
                'service_categories.extra_is_pricable as extraIsPricable',
                'service_categories.extra_code as extraCode',
                'service_categories.extra_price as extraPrice',
            ])->when(isset($filters['startAt']) && isset($filters['endAt']), function ($query) use ($filters) {
                return $query->whereBetween('tasks.created_at', [
                    Carbon::parse($filters['startAt'])->startOfDay(),
                    Carbon::parse($filters['endAt'])->endOfDay(),
                ]);
            })
            ->when(isset($filters['startAt']) && !isset($filters['endAt']), function ($query) use ($filters) {
                return $query->where('tasks.created_at', '>=', Carbon::parse($filters['startAt'])->startOfDay());
            })
            ->when(!isset($filters['startAt']) && isset($filters['endAt']), function ($query) use ($filters) {
                return $query->where('tasks.created_at', '<=', Carbon::parse($filters['endAt'])->endOfDay());
            })
            ->get();

        $invoiceIndexer = 0;
        // Format the data
        $formattedData = [];
        foreach ($allInvoices as $index =>$invoice) {
            $key = $invoice->invoiceId != null
                ? $invoice->invoiceId
                : "unassigned##{$invoice->clientId}";



            if (!in_array($key, array_column($formattedData, 'key'))) {
                $formattedData[] = [
                    'key' => $key,
                    'invoiceId' => $invoice->invoiceId??"",
                    'invoiceNumber' => $invoice->invoiceNumber ?? "",
                    'invoiceDate' => $invoice->invoiceCreatedAt ?? "",
                    'clientId' => $invoice->clientId ?? "",
                    'clientName' => $invoice->clientName ?? "",
                    'clientAddableToBulkInvoice' => $invoice->clientAddableToBulkInvoice ?? "",
                    'tasks' => [],
                    'totalPrice' => 0,
                    'totalPriceAfterDiscount' => 0,
                    'totalCosts' => 0,
                    'invoiceDiscountType' => $invoice->invoiceDiscountType,
                    'invoiceDiscountAmount' => $invoice->invoiceDiscountAmount,
                    'clientTotalTax' => $invoice->clientTotalTax,
                    'invoiceDiscount' => 0,
                    'totalInvoiceAfterDiscount' => 0
                ];


                $invoiceIndexer++;

            }

            $search = array_search($key, array_column($formattedData, 'key'));

            $servicePrice = $invoice->serviceCategoryAddToInvoice == ServiceCategoryAddToInvoiceStatus::ADD->value ? $invoice->serviceCategoryPrice : 0;

            $clientDiscount = ClientServiceDiscount::where('client_id', $invoice->clientId)
            ->whereRaw("FIND_IN_SET(?, service_category_ids)", [$invoice->serviceCategoryId])
            ->where('is_active', ClientServiceDiscountStatus::ACTIVE->value)
            ->first();

            $servicePriceAfterDiscount = $servicePrice;



            if ($clientDiscount && $servicePrice > 0) {

                $discountValue = $clientDiscount->discount;
                $isPercentage = $clientDiscount->type === ClientServiceDiscountType::PERCENTAGE->value;
                $isTax = $clientDiscount->category === ServiceDiscountCategory::TAX->value;

                // Apply tax (increase price) or discount (decrease price)
                if ($isTax) {
                    $servicePriceAfterDiscount = $isPercentage
                        ? $servicePrice * (1 + $discountValue / 100)
                        : $servicePrice + $discountValue;
                } else {
                    $servicePriceAfterDiscount = $isPercentage
                        ? $servicePrice * (1 - $discountValue / 100)
                        : max(0, $servicePrice - $discountValue);
                }

                $formattedData[$search]['totalPrice'] += $servicePrice;
                $formattedData[$search]['totalPriceAfterDiscount'] += $servicePriceAfterDiscount;


            } else {
                $formattedData[$search]['totalPrice'] += $servicePrice;
                $formattedData[$search]['totalPriceAfterDiscount'] += $servicePrice;

            }

            if($invoice->extraIsPricable == 1){
                $formattedData[$search]['totalCosts'] += $invoice->extraPrice;
            }


            $formattedData[$search]['tasks'][] = [
                'taskId' => $invoice->taskId,
                'taskTitle' => $invoice->taskTitle,
                'taskNumber' => $invoice->taskNumber,
                'serviceCategoryName' => $invoice->serviceCategoryName,
                'description' => $invoice->serviceCategoryName,
                'taskStatus' => $invoice->taskStatus,
                'price' =>$invoice->taskPrice ?? $servicePrice,
                'priceAfterDiscount' =>$invoice->taskPriceAfterDiscount??$servicePriceAfterDiscount,
                'extraPrice' => $invoice->extraPrice??0,
                'taskCreatedAt' => Carbon::parse($invoice->taskCreatedAt)->format('d/m/Y')
            ];


            if(count($formattedData) > 0) {
                $formattedData[$search]['additionalTax'] = $formattedData[$search]['clientTotalTax'];
                $formattedData[$search]['totalAfterAdditionalTax'] = $formattedData[$search]['totalPriceAfterDiscount'];

                $formattedData[$search]['invoiceDiscount'] = 0 ;
                $formattedData[$search]['totalInvoiceAfterDiscount'] = $formattedData[$search]['totalAfterAdditionalTax'];


                if($formattedData[$search]['additionalTax'] > 0) {
                    $formattedData[$search]['totalAfterAdditionalTax'] = $formattedData[$search]['totalAfterAdditionalTax'] + ($formattedData[$search]['totalAfterAdditionalTax'] * ($formattedData[$search]['additionalTax'] / 100));
                }

                //$formattedData[$search]['totalAfterAdditionalTax'] += $formattedData[$search]['totalCosts'];

                if($invoice->invoiceDiscountType == 0) {
                    $formattedData[$search]['invoiceDiscount'] = $invoice->invoiceDiscountAmount;
                    $formattedData[$search]['totalInvoiceAfterDiscount'] = ($formattedData[$search]['totalAfterAdditionalTax'] - $invoice->invoiceDiscountAmount);
                }

                if($invoice->invoiceDiscountType == 1) {
                    $formattedData[$search]['invoiceDiscount'] = $invoice->invoiceDiscountAmount;
                    $formattedData[$search]['totalInvoiceAfterDiscount'] = ($formattedData[$search]['totalAfterAdditionalTax'] - ($formattedData[$search]['totalAfterAdditionalTax'] * ($invoice->invoiceDiscountAmount / 100)));
                }



            }




        }

        // Paginate the formatted data
        $pageSize = $request->pageSize ?? 10;
        $paginatedData = PaginateCollection::paginate(collect($formattedData), $pageSize);

        return response()->json(new AllInvoiceCollection($paginatedData), 200);
    }


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

                $invoice = Invoice::find($invoiceData['invoiceId']);
                if (!$invoice) {
                    $invoice = Invoice::create([
                        'client_id' => $invoiceData['clientId'],
                        'end_at' => $endDate,
                        'payment_type_id' => $invoiceData['paymentTypeId'],
                        'discount_type' => $invoiceData['discountType']??null,
                        'discount_amount' => $invoiceData['discountAmount'],
                        'bank_account_id' => $invoiceData['bankAccountId'],
                    ]);
                }

                $invoiceTasks = $invoiceData['taskIds'];
                    foreach ($invoiceTasks as $index =>  $taskId) {
                        if (!$task = Task::find($taskId)) {
                            continue;
                        }

                        /*$clientDiscount=  ClientServiceDiscount::where('client_id', $invoiceData['clientId'])->first();*/

                        $clientDiscount = ClientServiceDiscount::where('client_id', $invoiceData['clientId'])
                        ->whereRaw("FIND_IN_SET(?, service_category_ids)", [$task->service_category_id])
                        ->where('is_active', ClientServiceDiscountStatus::ACTIVE->value)
                        ->first();





                        $servicePrice = $task->serviceCategory->price ?? 0;
                        $priceAfterDiscount = $servicePrice;


                        if (!empty($clientDiscount)) {
                            $discountValue = $clientDiscount->discount;
                            $isPercentage = $clientDiscount->type === ClientServiceDiscountType::PERCENTAGE->value;


                            if ($clientDiscount->category === ServiceDiscountCategory::TAX->value) {
                                // Apply tax: increase price
                                $priceAfterDiscount = $isPercentage
                                    ? $servicePrice * (1 + $discountValue / 100)
                                    : $servicePrice + $discountValue;
                            } else {
                                // Apply discount: decrease price
                                $priceAfterDiscount = $isPercentage
                                    ? $servicePrice * (1 - $discountValue / 100)
                                    : max(0, $servicePrice - $discountValue);
                            }
                        }


                        $extraPrice = 0;

                        $serviceCategory = ServiceCategory::find($task->service_category_id);


                        if($serviceCategory->extra_is_pricable == 1) {
                            $extraPrice = $serviceCategory->extra_price ?? 0;
                        }



                        $task->update([
                            "price" => $servicePrice,
                            "price_after_discount" => $priceAfterDiscount,
                            "invoice_id" => $invoice->id,
                        ]);

                        $invoiceDetail = new InvoiceDetail([
                            'invoice_id' => $invoice->id, // Invoice ID
                            'price' => $servicePrice,
                            'price_after_discount' => $priceAfterDiscount,
                            'extra_price' => $extraPrice
                        ]);

                        $task->invoiceDetails()->save($invoiceDetail);

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

    public function edit(Request $request)
    {
        $invoice = Invoice::with('invoiceDetails')->find($request->invoiceId);
        $invoiceDetailsData = [];

        $invoiceableType = "";

        $startAt = Carbon::parse($invoice->create_at)->format('d/m/Y');


        foreach ($invoice->invoiceDetails as $invoiceDetail) {
            if($invoiceDetail->invoiceable_type == Task::class) {
                $invoiceableType = "task";
            }

            if($invoiceDetail->invoiceable_type == ClientPayInstallment::class || $invoiceDetail->invoiceable_type == ClientPayInstallmentSubData::class) {
                $invoiceableType = "recurring";
            }


            if($invoiceDetail->invoiceable_type == ClientPayInstallment::class) {
                $clientPayInstallment = ClientPayInstallment::find($invoiceDetail->invoiceable_id)->start_at;
                $startAt = Carbon::parse($clientPayInstallment)->format('Y-m-d');
            }


            $invoiceDetailsData[] = [
                'price' => $invoiceDetail->price,
                'priceAfterDiscount' => $invoiceDetail->price_after_discount,
                'invoiceDetailId' => $invoiceDetail->id,
                'invoiceableType' => $invoiceableType,
                'invoiceableId' => $invoiceDetail->invoiceable_id??'',
                'description' => $invoiceDetail->description??'',
            ];
        }


        $clientAddress = ClientAddress::where('client_id', $invoice->client_id)->first();


        return response()->json([
            'data' => [
                'invoiceNumber' => $invoice->number,
                'invoiceId' => $invoice->id,
                'startAt' => $startAt,
                'endAt' => $invoice->end_at,
                'clientId' => $invoice->client_id,
                'clientName' => $invoice->client->ragione_sociale,
                'clientPiva' => $invoice->client->iva??'',
                'clientCodeFiscale' => $invoice->client->cf??'',
                'clientAddress' => $clientAddress->address??'',
                'paymentTypeId' => $invoice->payment_type_id??'',
                'discountType' => $invoice->discount_type??'',
                'discountAmount' => $invoice->discount_amount??0,
                'bankAccountId' => $invoice->bank_account_id??'',
                'invoiceDetails' => $invoiceDetailsData
            ]
        ]);
    }

    private function assignedInvoices(Request $request){
        $filters = $request->filter ?? null;

        $allInvoices = DB::table('invoice_details')
            ->leftJoin('invoices', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->leftJoin('clients', 'invoices.client_id', '=', 'clients.id')
            ->leftJoin('client_pay_installments', function($join) {
                $join->on('invoice_details.invoiceable_id', '=', 'client_pay_installments.id')
                     ->where('invoice_details.invoiceable_type', '=', 'App\\Models\\Client\\ClientPayInstallment');
            })
            ->whereNull('invoices.deleted_at')
            //->whereNull('invoice_details.deleted_at')
            ->when($filters['unassigned'] == 0, function ($query) {
                $query->whereDate('invoice_details.created_at', '>', Carbon::parse('2026-01-04'));
            })
            ->select([
                'invoices.id as invoiceId',
                'invoices.created_at as invoiceCreatedAt',
                'invoices.invoice_xml_number as invoiceXmlNumber',
                'clients.id as clientId',
                'clients.total_tax as clientTotalTax',
                'clients.ragione_sociale as clientName',
                'clients.addable_to_bulk_invoice as clientAddableToBulkInvoice',
                'invoices.number as invoiceNumber',
                'invoices.discount_type as invoiceDiscountType',
                'invoices.discount_amount as invoiceDiscountAmount',
                'invoice_details.id as invoiceDetailId',
                'invoice_details.price as invoiceDetailPrice',
                'invoice_details.price_after_discount as invoiceDetailPriceAfterDiscount',
                'invoice_details.invoiceable_id as invoiceableId',
                'invoice_details.invoiceable_type as invoiceableType',
                'invoice_details.extra_price as invoiceDetailExtraPrice',
                'invoice_details.description as invoiceDetailDescription',
                'client_pay_installments.start_at as installmentStartAt',
                /*'tasks.id as taskId',
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
                'service_categories.add_to_invoice as serviceCategoryAddToInvoice',
                'service_categories.extra_is_pricable as extraIsPricable',
                'service_categories.extra_code as extraCode',
                'service_categories.extra_price as extraPrice',*/
            ])
            ->when(isset($filters['clientId']), function ($query) use ($filters) {
                return $query->where('clients.id', $filters['clientId']);
            })
            ->when(isset($filters['startAt']) && isset($filters['endAt']), function ($query) use ($filters) {
                return $query->where(function($q) use ($filters) {
                    $q->whereBetween(DB::raw('COALESCE(client_pay_installments.start_at, invoices.created_at)'), [
                        Carbon::parse($filters['startAt'])->startOfDay(),
                        Carbon::parse($filters['endAt'])->endOfDay(),
                    ]);
                })->whereBetween('invoices.end_at', [
                    Carbon::parse($filters['startAt'])->startOfDay(),
                    Carbon::parse($filters['endAt'])->endOfDay(),
                ]);
            })
            ->when(isset($filters['startAt']) && !isset($filters['endAt']), function ($query) use ($filters) {
                return $query->where(function($q) use ($filters) {
                    $q->where(DB::raw('COALESCE(client_pay_installments.start_at, invoices.created_at)'), '>=', Carbon::parse($filters['startAt'])->startOfDay());
                })->where('invoices.end_at', '>=', Carbon::parse($filters['startAt'])->startOfDay());
            })
            ->when(!isset($filters['startAt']) && isset($filters['endAt']), function ($query) use ($filters) {
                return $query->where(function($q) use ($filters) {
                    $q->where(DB::raw('COALESCE(client_pay_installments.start_at, invoices.created_at)'), '<=', Carbon::parse($filters['endAt'])->endOfDay());
                })->where('invoices.end_at', '<=', Carbon::parse($filters['endAt'])->endOfDay());
            })
            ->when(isset($filters['hasXmlNumber']), function ($query) use ($filters) {
                if ($filters['hasXmlNumber'] == 1) {
                    return $query->whereNotNull('invoices.invoice_xml_number');
                } elseif ($filters['hasXmlNumber'] == 0) {
                    return $query->whereNull('invoices.invoice_xml_number');
                }
            })
            ->get();

        // Format the data
        $formattedData = [];
        $invoiceIndexer = 0;
        foreach ($allInvoices as $index =>$invoice) {
            $key = $invoice->invoiceId;

            $invoiceClientPayInstallment = InvoiceDetail::where('invoice_id', $invoice->invoiceId)->where('invoiceable_type', ClientPayInstallment::class)->first();

            $invoiceDate = $invoice->installmentStartAt ?? $invoice->invoiceCreatedAt;

            if (!in_array($key, array_column($formattedData, 'key'))) {
                $formattedData[] = [
                    'key' => $key,
                    'invoiceId' => $invoice->invoiceId??"",
                    'invoiceNumber' => $invoice->invoiceNumber ?? "",
                    'invoiceXmlNumber' => $invoice->invoiceXmlNumber ?? "",
                    'clientId' => $invoice->clientId ?? "",
                    'clientName' => $invoice->clientName ?? "",
                    'clientAddableToBulkInvoice' => $invoice->clientAddableToBulkInvoice ?? "",
                    'tasks' => [],
                    'totalPrice' => 0,
                    'totalPriceAfterDiscount' => 0,
                    'totalCosts' => 0,
                    'invoiceDiscountType' => $invoice->invoiceDiscountType,
                    'invoiceDiscountAmount' => $invoice->invoiceDiscountAmount,
                    'clientTotalTax' => $invoice->clientTotalTax,
                    'invoiceDiscount' => 0,
                    'totalInvoiceAfterDiscount' => 0,
                    'invoiceDate' => $invoiceDate
                ];

                /*if(count($formattedData) >1) {
                    $formattedData[$invoiceIndexer - 1]['totalAfterAdditionalTax'] += ($formattedData[$invoiceIndexer - 1]['totalAfterAdditionalTax'] * .22);
                }*/
                    $invoiceIndexer++;


            }


            $search = array_search($key, array_column($formattedData, 'key'));


            $formattedData[$search]['totalPrice'] += $invoice->invoiceDetailPrice;
            $formattedData[$search]['totalPriceAfterDiscount'] += $invoice->invoiceDetailPriceAfterDiscount;
            $formattedData[$search]['totalCosts'] += $invoice->invoiceDetailExtraPrice??0;

            $task = $invoice->invoiceableType == Task::class ? Task::with('serviceCategory')->find($invoice->invoiceableId) : "";

            $description = "";

            if ($task && $description == null) {
                $description = $task->serviceCategory->name;
            } elseif($invoice->invoiceableType == ClientPayInstallment::class && $description == null) {
                $description = ClientPayInstallment::with('parameterValue')->find($invoice->invoiceableId)?->parameterValue?->description;


            }elseif($invoice->invoiceableType == ClientPayInstallmentSubData::class && $description == null) {
                $description = ClientPayInstallment::with('parameterValue')->find($invoice->invoiceableId)?->parameterValue?->description;
            }

            if($invoice->invoiceDetailDescription != null) {
                $description = $invoice->invoiceDetailDescription;
            }

            $formattedData[$search]['tasks'][] = [
                'taskId' => $invoice->invoiceDetailId,
                'taskTitle' => $task->title??"",
                'taskNumber' => $task->number??"",
                'serviceCategoryName' => $description??'',
                'description' => $description??'',
                'price' =>$invoice->invoiceDetailPrice,
                'priceAfterDiscount' =>$invoice->invoiceDetailPriceAfterDiscount,
                'extraPrice' => $invoice->invoiceDetailExtraPrice??0,
                //'taskCreatedAt' => Carbon::parse($invoice->taskCreatedAt)->format('d/m/Y')
            ];


            if(count($formattedData) > 0) {
                $formattedData[$search]['additionalTax'] = $formattedData[$search]['clientTotalTax'];
                $formattedData[$search]['totalAfterAdditionalTax'] = $formattedData[$search]['totalPriceAfterDiscount'] + ($formattedData[$search]['totalPriceAfterDiscount'] * .22);

                $formattedData[$search]['invoiceDiscount'] = 0 ;
                $formattedData[$search]['totalInvoiceAfterDiscount'] = $formattedData[$search]['totalAfterAdditionalTax'];

                if($formattedData[$search]['additionalTax'] > 0) {
                    $formattedData[$search]['totalAfterAdditionalTax'] = $formattedData[$search]['totalAfterAdditionalTax'] + ($formattedData[$search]['totalAfterAdditionalTax'] * ($formattedData[$search]['additionalTax'] / 100));
                }

                if($invoice->invoiceDiscountType == 0) {
                    $formattedData[$search]['invoiceDiscount'] = $invoice->invoiceDiscountAmount;
                    $formattedData[$search]['totalInvoiceAfterDiscount'] = ($formattedData[$search]['totalAfterAdditionalTax'] - $invoice->invoiceDiscountAmount);
                }

                if($invoice->invoiceDiscountType == 1) {
                    $formattedData[$search]['invoiceDiscount'] = $invoice->invoiceDiscountAmount;
                    $formattedData[$search]['totalInvoiceAfterDiscount'] = ($formattedData[$search]['totalAfterAdditionalTax'] - ($formattedData[$search]['totalAfterAdditionalTax'] * ($invoice->invoiceDiscountAmount / 100)));
                }

            }

        }

        // Paginate the formatted data
        $pageSize = $request->pageSize ?? 10;
        $paginatedData = PaginateCollection::paginate(collect($formattedData), $pageSize);

        return response()->json(new AllInvoiceCollection($paginatedData), 200);
    }

    public function update(Request $request)
    {
        try {
            DB::beginTransaction();

            $invoice = Invoice::find($request->invoiceId);

            $endDate = Carbon::parse($request->endAt);

            // if ($endDate->format('d-m') === '31-08' || $endDate->format('d-m') === '31-12') {
            //     $endDate->addDays(10);
            // }

            $invoice->end_at = $endDate;
            $invoice->payment_type_id = $request->paymentTypeId;
            $invoice->discount_type = $request->discountType;
            $invoice->discount_amount = $request->discountAmount;
            $invoice->bank_account_id = $request->bankAccountId;

            $invoice->save();

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
     * Add tasks to existing invoice
     *
     * Request format:
     * {
     *   "invoiceId": 341,
     *   "taskIds": [1, 2, 3]
     * }
     */
    public function addTasksToInvoice(Request $request)
    {
        try {
            DB::beginTransaction();

            $invoice = Invoice::find($request->invoiceId);

            if (!$invoice) {
                return response()->json([
                    'message' => 'Invoice not found'
                ], 404);
            }

            $taskIds = $request->taskIds ?? [];

            foreach ($taskIds as $taskId) {
                $task = Task::find($taskId);

                if (!$task) {
                    continue;
                }

                // Get client discount if exists
                $clientDiscount = ClientServiceDiscount::where('client_id', $invoice->client_id)
                    ->whereRaw("FIND_IN_SET(?, service_category_ids)", [$task->service_category_id])
                    ->where('is_active', ClientServiceDiscountStatus::ACTIVE->value)
                    ->first();

                $servicePrice = $task->serviceCategory->price ?? 0;
                $priceAfterDiscount = $servicePrice;

                // Apply discount or tax if exists
                if (!empty($clientDiscount)) {
                    $discountValue = $clientDiscount->discount;
                    $isPercentage = $clientDiscount->type === ClientServiceDiscountType::PERCENTAGE->value;

                    if ($clientDiscount->category === ServiceDiscountCategory::TAX->value) {
                        // Apply tax: increase price
                        $priceAfterDiscount = $isPercentage
                            ? $servicePrice * (1 + $discountValue / 100)
                            : $servicePrice + $discountValue;
                    } else {
                        // Apply discount: decrease price
                        $priceAfterDiscount = $isPercentage
                            ? $servicePrice * (1 - $discountValue / 100)
                            : max(0, $servicePrice - $discountValue);
                    }
                }

                $extraPrice = 0;
                $serviceCategory = ServiceCategory::find($task->service_category_id);

                if ($serviceCategory && $serviceCategory->extra_is_pricable == 1) {
                    $extraPrice = $serviceCategory->extra_price ?? 0;
                }

                // Update task with invoice_id and prices
                $task->update([
                    "price" => $servicePrice,
                    "price_after_discount" => $priceAfterDiscount,
                    "invoice_id" => $invoice->id,
                ]);

                // Create invoice detail
                $invoiceDetail = new InvoiceDetail([
                    'invoice_id' => $invoice->id,
                    'price' => $servicePrice,
                    'price_after_discount' => $priceAfterDiscount,
                    'extra_price' => $extraPrice
                ]);

                $task->invoiceDetails()->save($invoiceDetail);
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
}
