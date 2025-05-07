<?php

namespace App\Http\Controllers\Api\Private\Reports;

use App\Http\Controllers\Controller;
use App\Models\Client\Client;
use App\Models\Client\ClientAddress;
use App\Models\Client\ClientBankAccount;
use App\Models\Client\ClientPayInstallment;
use App\Models\Client\ClientPayInstallmentSubData;
use App\Models\Invoice\Invoice;
use App\Models\Parameter\ParameterValue;
use App\Models\Task\Task;
use App\Services\Reports\ReportService;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoiceReportExportController extends Controller
{

    protected $reportService;
    public function  __construct(ReportService $reportService)
    {
        //$this->middleware('auth:api');
        //$this->middleware('permission:all_reports', ['only' => ['__invoke']]);
        $this->reportService =$reportService;
    }
    public function index(Request $request)
    {

        if($request->type == 'pdf'){
            $invoice = Invoice::find($request->invoiceIds[0]);

            $invoiceItems = DB::table('invoice_details')
                ->where('invoice_details.invoice_id', $invoice->id)
                ->select([
                    'invoice_details.price_after_discount',
                    'invoice_details.invoiceable_id',
                    'invoice_details.invoiceable_type',
                    'invoice_details.description'
                ])->get();

            $invoiceItemsData = [];
            $totalTax = 0;
            $invoiceTotal = 0;

            foreach ($invoiceItems as $index => $invoiceItem) {

                if($invoiceItem->invoiceable_type == Task::class) {
                    $invoiceItemData = Task::with('serviceCategory')->find($invoiceItem->invoiceable_id);
                } elseif($invoiceItem->invoiceable_type == ClientPayInstallment::class || $invoiceItem->invoiceable_type) {
                    $invoiceItemData = ClientPayInstallment::with('parameterValue')->find($invoiceItem->invoiceable_id);
                } elseif($invoiceItem->invoiceable_type == ClientPayInstallmentSubData::class) {
                    $invoiceItemData = ClientPayInstallmentSubData::with('parameterValue')->find($invoiceItem->invoiceable_id);
                    dd($invoiceItemData);
                }

                $invoiceItemsData[] = [
                    'description' =>  $invoiceItem->invoiceable_type == Task::class ?
                    $invoiceItemData->serviceCategory->name :$invoiceItemData->parameterValue?->description??$invoiceItem->description,
                    'priceAfterDiscount' => $invoiceItem->price_after_discount,
                    'additionalTaxPercentage' => 22
                ];


                $totalTax += $invoiceItem->price_after_discount * 0.22;

                $invoiceTotal += $invoiceItem->price_after_discount;

                if($invoiceItem->invoiceable_type == Task::class && $invoiceItemData->serviceCategory->extra_is_pricable) {
                    $invoiceItemsData[] = [
                    'description' =>  $invoiceItemData->serviceCategory->extra_price_description,
                    'priceAfterDiscount' => $invoiceItemData->serviceCategory->extra_price,
                    'additionalTaxPercentage' => 0
                    ];

                    $invoiceTotal += $invoiceItemData->serviceCategory->extra_price;
                }


            }



            $client = Client::find($invoice->client_id);

            if($client->total_tax > 0){
                $invoiceItemsData[] = [
                    'description' =>  $client->total_tax_description??'',
                    'priceAfterDiscount' => $client->total_tax > 0 ? $invoiceTotal * ($client->total_tax / 100): 0,
                    'additionalTaxPercentage' => 0
                ];

                $invoiceTotal += $invoiceTotal * ($client->total_tax / 100);
            }

            $clientAddress = ClientAddress::where('client_id',$client->id)->first();

            $clientBankAccount = ClientBankAccount::where('client_id',$client->id)->first();

            if($clientAddress){
                $clientAddressFormatted = $clientAddress->address;
            }

            if($clientBankAccount){
                $clientBankAccountFormatted = $clientBankAccount->iban;
            }


            if($invoice->discount_amount > 0){
                if($invoice->discount_type == 0){
                    $invoiceTotal -= $invoiceTotal * ($invoice->discount_amount / 100);
                }else{
                    $invoiceTotal -= $invoice->discount_amount;
                }
                $invoiceItemsData[] = [
                    'description' =>  "sconto",
                    'priceAfterDiscount' => $client->discount_type == 0 ? $invoice->discount_amount . "%" : $invoice->discount_amount,
                    'additionalTaxPercentage' => 0
                ];

            }


            $paymentMethod = ParameterValue::find($invoice->payment_type_id ?? null);

            $pdf = PDF::loadView('invoice_pdf_report', [
                'invoice' => $invoice,
                'invoiceItems' => $invoiceItemsData,
                'invoiceTotalTax' => $totalTax,
                'invoiceTotal' => $invoiceTotal,
                'invoiceTotalWithTax' => $invoiceTotal + $totalTax,
                'client' => $client,
                'clientAddress' => $clientAddressFormatted ?? "",
                'clientBankAccount' => $clientBankAccountFormatted ?? "",
                'paymentMethod' => $paymentMethod->parameter_value ?? "",
            ]);

            // Define file path
            $fileName = 'invoice_' . $invoice->id . '.pdf';
            $path = 'exportedInvoices/' . $fileName;

            // Save PDF to storage
            Storage::disk('public')->put($path, $pdf->output());

            // Generate public URL
            $url = asset('storage/' . $path);

            return response()->json(['path' => env('APP_URL') . $url]);

        } else if($request->type == 'csv'){
            $csvFileName = 'exportedInvoices/user_' . time() . '.csv'; // Store inside 'storage/app/public/invoices'
            $csvPath = storage_path('app/public/' . $csvFileName);

            $csvFile = fopen($csvPath, 'w');

            // ✅ Correct headers with semicolon delimiter
            $headers = ['Cliente', 'Descrizione', 'Prezzo unitario', 'Quantità', 'Prezzo Totale', 'Data prestazione'];
            fwrite($csvFile, implode(';', $headers) . "\n"); // ✅ Manually write headers

            foreach ($request->invoiceIds as $invoiceId) {
                $invoice = Invoice::find($invoiceId);
                $tasks = Task::where('invoice_id', $invoice->id)->get();
                $client = Client::find($invoice->client_id);

                foreach ($tasks as $task) {
                    $row = [
                        $client->iva ?? $client->cf,
                        $task->serviceCategory->name ?? '',
                        $task->price_after_discount,
                        1,
                        $task->price_after_discount * 1,
                        Carbon::now()->format('d/m/Y')
                    ];
                    fwrite($csvFile, implode(';', $row) . "\n"); // ✅ Manually write row
                }
            }

            fclose($csvFile); // Always close the file

            // Ensure storage is publicly linked: Run `php artisan storage:link`
            $url = asset('storage/' . $csvFileName);

            return response()->json(data: ['path' => env('APP_URL') . $url]);


        } else{
            return response()->json(['message' => 'no such export type'], 401);
        }

    }
}
