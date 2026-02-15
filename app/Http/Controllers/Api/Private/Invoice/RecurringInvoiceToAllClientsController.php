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

class RecurringInvoiceToAllClientsController extends Controller
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


            $clients = Client::all();

            //$client = Client::find($createTaskRequest->clientId);

            foreach ($clients as $client) {
                if(count($client->payInstallments) > 0){
                    $client->has_recurring_invoice = true;
                    $client->save();
                }

                $bankAccount = ParameterValue::where('parameter_id', 7)->where('is_default', 1)->first();

                $payInstallments = ClientPayInstallment::where('client_id', $client->id)->get();

                foreach ($payInstallments as  $payInstallmentData) {

                    $clientEndDataAdd = ParameterValue::where('id', $payInstallmentData->payment_type_id)->first();
                    
                    if(!$clientEndDataAdd) {
                        continue; // Skip if payment type not found
                    }

                    $clientEndDataAddMonth = ceil($clientEndDataAdd->description / 30);


                    $allowedDaysToPay = $client->allowed_days_to_pay ?? 0; // Fetch from the client table

                    $startDate = Carbon::parse($payInstallmentData->start_at);
                    $endDate = $startDate->copy()->addMonths($clientEndDataAddMonth)->subDays(1);


                    $isSpecialMonthEnd = in_array($endDate->format('m-d'), ['08-31', '12-31']);

                    if ($isSpecialMonthEnd) {
                        $endDate->addDays(10);
                    } else {
                        $endDate->addDays($allowedDaysToPay);
                    }

                    $invoice = Invoice::create([
                        'client_id' => $client->id,
                        'end_at' => $endDate,
                        'payment_type_id' => $payInstallmentData->payment_type_id,
                        'discount_type' => null,
                        'discount_amount' => 0,
                        'bank_account_id' => $bankAccount?->id??null,
                    ]);


                    $payInstallment = ClientPayInstallment::find($payInstallmentData->id);

                    $payInstallmentDescription = ParameterValue::where('id', $payInstallment->parameter_value_id)->first();
                    

                    $invoiceDetail = new InvoiceDetail([
                        'invoice_id' => $invoice->id, // Invoice ID
                        'price' => $payInstallmentData->amount??0,
                        'price_after_discount' => $payInstallmentData->amount??0,
                        'description' => $payInstallmentDescription?->description??''
                    ]);
                    


                    $payInstallment->invoiceDetails()->save($invoiceDetail);

                    $payInstallmentsSubData = ClientPayInstallmentSubData::where('client_pay_installment_id', $payInstallment->id)->get();

                    foreach ($payInstallmentsSubData as $payInstallmentSubData) {
                        



                        $payInstallmentSubDatatDescription = ParameterValue::where('id', $payInstallmentSubData->parameter_value_id)->first();

                        $invoiceDetail = new InvoiceDetail([
                            'invoice_id' => $invoice->id, // Invoice ID
                            'price' => $payInstallmentSubData->price??0,
                            'price_after_discount' => $payInstallmentSubData->price??0,
                            'description' => $payInstallmentSubDatatDescription?->description??''
                        ]);
                        

                        $payInstallmentSubData->invoiceDetails()->save($invoiceDetail);
                    }

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
