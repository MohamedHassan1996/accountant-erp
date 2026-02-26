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

        if (count($client->payInstallments) > 0) {
            $client->has_recurring_invoice = true;
            $client->save();
        }

        $bankAccount = ParameterValue::where('parameter_id', 7)
            ->where('is_default', 1)
            ->first();

        // Get holidays from parameter_values where parameter_order = 11
        // Holidays are stored as d/m format (e.g., "1/3" for March 1st)
        $holidays = ParameterValue::where('parameter_order', 11)
            ->pluck('parameter_value')
            ->map(function($date) {
                // Convert d/m format to Y-m-d format with current year
                $parts = explode('/', $date);
                if (count($parts) == 2) {
                    $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                    $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                    $year = Carbon::now()->year;
                    return "{$year}-{$month}-{$day}";
                }
                // Skip invalid date formats
                return null;
            })
            ->filter() // Remove null values
            ->toArray();

        foreach ($createTaskRequest->payInstallments as $index => $payInstallmentData) {

            // Calculate start date based on installment index
            // Start from January (month 1) and add months based on payment frequency
            $clientEndDataAdd = ParameterValue::where(
                'id',
                $payInstallmentData['paymentTypeId']
            )->first();

            $clientEndDataAddMonth = ceil($clientEndDataAdd->description / 30);

            // Calculate which month this installment should start
            // First installment starts in January, then add months based on frequency
            $monthsToAdd = $index * $clientEndDataAddMonth;

            $startDate = Carbon::now()->startOfYear()->addMonths($monthsToAdd)->day(1);

            // Adjust start date if it falls on weekend or holiday
            $startDate = $this->adjustForWeekendsAndHolidays($startDate, $holidays);

            /**
             * ✅ 1. استخدم endAt من الريكوست لو موجود
             * 🔁 2. وإلا احسبه بالمنطق الجديد
             */
            if (!empty($payInstallmentData['endAt'])) {

                $endDate = Carbon::parse($payInstallmentData['endAt']);

            } else {

                $clientEndDataAdd = ParameterValue::where(
                    'id',
                    $payInstallmentData['paymentTypeId']
                )->first();

                $clientEndDataAddMonth = ceil($clientEndDataAdd->description / 30);

                // Calculate end date: add months then go back one month and get last day
                // Example: 30 days (1 month): start 01/01 + 1 month - 1 month = 01/01, endOfMonth = 31/01
                // Example: 60 days (2 months): start 01/01 + 2 months - 1 month = 01/02, endOfMonth = 28/02
                $endDate = $startDate->copy()
                    ->addMonths($clientEndDataAddMonth)
                    ->subMonth()
                    ->endOfMonth();

                $allowedDaysToPay = $client->allowed_days_to_pay ?? 0;

                $isSpecialMonthEnd = in_array(
                    $endDate->format('m-d'),
                    ['08-31', '12-31']
                );

                if ($isSpecialMonthEnd) {
                    $endDate->addDays(10);
                } else {
                    $endDate->addDays($allowedDaysToPay);
                }

                // Adjust end date if it falls on weekend or holiday
                $endDate = $this->adjustForWeekendsAndHolidays($endDate, $holidays);
            }

            $invoice = Invoice::create([
                'client_id'        => $createTaskRequest->clientId,
                'end_at'           => $endDate,
                'payment_type_id'  => $createTaskRequest->paymentTypeId,
                'discount_type'    => null,
                'discount_amount'  => 0,
                'bank_account_id'  => $bankAccount?->id ?? null,
            ]);

            $payInstallment = ClientPayInstallment::find(
                $payInstallmentData['payInstallmentId']
            );

            // Save the calculated start_at date to the installment
            $payInstallment->start_at = $startDate;
            $payInstallment->save();

            $payInstallmentDescription = ParameterValue::where(
                'id',
                $payInstallment->parameter_value_id
            )->first();

            $invoiceDetail = new InvoiceDetail([
                'invoice_id'            => $invoice->id,
                'price'                 => $payInstallmentData['amount'],
                'price_after_discount'  => $payInstallmentData['amount'],
                'description'           => $payInstallmentDescription?->description ?? ''
            ]);

            $payInstallment->invoiceDetails()->save($invoiceDetail);

            foreach ($payInstallmentData['payInstallmentSubData'] as $payInstallmentSubData) {

                $payInstallmentSubDataDb = ClientPayInstallmentSubData::find(
                    $payInstallmentSubData['payInstallmentSubDataId']
                );

                $payInstallmentSubDataDescription = ParameterValue::where(
                    'id',
                    $payInstallmentSubDataDb->parameter_value_id
                )->first();

                $invoiceDetail = new InvoiceDetail([
                    'invoice_id'            => $invoice->id,
                    'price'                 => $payInstallmentSubData['price'],
                    'price_after_discount'  => $payInstallmentSubData['price'],
                    'description'           => $payInstallmentSubDataDescription?->description ?? ''
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

    /**
     * Adjust date if it falls on weekend (Saturday/Sunday) or holiday
     * Move to next Monday or next working day
     */
    private function adjustForWeekendsAndHolidays(Carbon $date, array $holidays): Carbon
    {
        $adjustedDate = $date->copy();

        // Keep adjusting until we find a working day
        while (true) {
            $dayOfWeek = $adjustedDate->dayOfWeek;
            $dateString = $adjustedDate->format('Y-m-d');

            // Check if Saturday (6) or Sunday (0)
            if ($dayOfWeek == Carbon::SATURDAY || $dayOfWeek == Carbon::SUNDAY) {
                // Move to next Monday
                $adjustedDate->next(Carbon::MONDAY);
                continue;
            }

            // Check if it's a holiday
            if (in_array($dateString, $holidays)) {
                $adjustedDate->addDay();
                continue;
            }

            // It's a working day
            break;
        }

        return $adjustedDate;
    }

}
