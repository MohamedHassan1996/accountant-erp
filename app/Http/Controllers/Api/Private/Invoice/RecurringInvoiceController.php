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
                $endDate = Carbon::parse($payInstallmentData['endAt']);

                if ($endDate->format('d-m') === '31-08' || $endDate->format('d-m') === '31-12') {
                    $endDate->addDays(10);
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

                $invoiceDetail = new InvoiceDetail([
                    'invoice_id' => $invoice->id, // Invoice ID
                    'price' => $payInstallmentData['amount'],
                    'price_after_discount' => $payInstallmentData['amount']
                ]);

                $payInstallment->invoiceDetails()->save($invoiceDetail);


                foreach ($payInstallmentData['payInstallmentSubData'] as $key => $payInstallmentSubData) {
                    $payInstallmentSubDataDb = ClientPayInstallmentSubData::find($payInstallmentSubData['payInstallmentSubDataId']);

                    $invoiceDetail = new InvoiceDetail([
                        'invoice_id' => $invoice->id, // Invoice ID
                        'price' => $payInstallmentSubData['amount'],
                        'price_after_discount' => $payInstallmentSubData['amount']
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
