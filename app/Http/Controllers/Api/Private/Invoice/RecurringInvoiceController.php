<?php

namespace App\Http\Controllers\Api\Private\Invoice;

use Illuminate\Http\Request;
use App\Models\Invoice\Invoice;
use App\Services\Task\TaskService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Client\Client;
use App\Models\Client\ClientPayInstallment;
use App\Models\Client\ClientPayInstallmentSubData;
use App\Models\Invoice\InvoiceDetail;
use App\Models\Parameter\ParameterValue;
use Carbon\Carbon;

class RecurringInvoiceController extends Controller
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->middleware('auth:api');
        //$this->middleware('permission:create_recurring_invoice', ['only' => ['create']]);
        $this->taskService = $taskService;
    }

    public function create(Request $createTaskRequest)
    {
        try {
            DB::beginTransaction();


            $client = Client::find($createTaskRequest->clientId);

            if(count($client->payInstallments) > 0){
                $client->has_recurring_invoice = true;
                $client->save();
            }

            $bankAccount = ParameterValue::where('parameter_id', 7)->where('is_default', 1)->first();

            foreach ($createTaskRequest->payInstallments as  $payInstallmentData) {
                // $endDate = Carbon::parse($payInstallmentData['endAt']);

                // if ($endDate->format('d-m') === '31-08' || $endDate->format('d-m') === '31-12') {
                //     $endDate->addDays(10);
                // }

                $clientEndDataAdd = ParameterValue::where('id', $payInstallmentData['paymentTypeId'])->first();

                $clientEndDataAddMonth = ceil($clientEndDataAdd->description / 30);


                $allowedDaysToPay = $client->allowed_days_to_pay ?? 0; // Fetch from the client table

                $startDate = Carbon::parse($payInstallmentData['startAt']);
                $endDate = $startDate->copy()->addMonths($clientEndDataAddMonth)->endOfMonth();

                $isSpecialMonthEnd = in_array($endDate->format('m-d'), ['08-31', '12-31']);

                if ($isSpecialMonthEnd) {
                    $endDate->addDays(10);
                } else {
                    $endDate->addDays($allowedDaysToPay);
                }
                $invoice = Invoice::create([
                    'client_id' => $createTaskRequest->clientId,
                    'end_at' => $endDate,
                    'payment_type_id' => $createTaskRequest->paymentTypeId,
                    'discount_type' => null,
                    'discount_amount' => 0,
                    'bank_account_id' => $bankAccount?->id??null,
                ]);


                $payInstallment = ClientPayInstallment::find($payInstallmentData['payInstallmentId']);

                $payInstallmentDescription = ParameterValue::where('id', $payInstallment->parameter_value_id)->first();

                $invoiceDetail = new InvoiceDetail([
                    'invoice_id' => $invoice->id, // Invoice ID
                    'price' => $payInstallmentData['amount'],
                    'price_after_discount' => $payInstallmentData['amount'],
                    'description' => $payInstallmentDescription?->description??''
                ]);

                $payInstallment->invoiceDetails()->save($invoiceDetail);


                foreach ($payInstallmentData['payInstallmentSubData'] as $key => $payInstallmentSubData) {
                    $payInstallmentSubDataDb = ClientPayInstallmentSubData::find($payInstallmentSubData['payInstallmentSubDataId']);


                    $payInstallmentSubDataDbtDescription = ParameterValue::where('id', $payInstallmentSubDataDb->parameter_value_id)->first();

                    $invoiceDetail = new InvoiceDetail([
                        'invoice_id' => $invoice->id, // Invoice ID
                        'price' => $payInstallmentSubData['price'],
                        'price_after_discount' => $payInstallmentSubData['price'],
                        'description' => $payInstallmentSubDataDbtDescription?->description??''
                    ]);

                    $payInstallmentSubDataDb->invoiceDetails()->save($invoiceDetail);
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
}
