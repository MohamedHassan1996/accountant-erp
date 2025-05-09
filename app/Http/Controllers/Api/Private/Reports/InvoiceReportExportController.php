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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;


class InvoiceReportExportController extends Controller
{

    protected $reportService;
    public function  __construct(ReportService $reportService)
    {
        //$this->middleware('auth:api');
        //$this->middleware('permission:all_reports', ['only' => ['__invoke']]);
        $this->reportService =$reportService;
    }
    /*public function index(Request $request)
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
                } elseif($invoiceItem->invoiceable_type == ClientPayInstallment::class) {
                    $invoiceItemData = ClientPayInstallment::with('parameterValue')->find($invoiceItem->invoiceable_id);
                } elseif($invoiceItem->invoiceable_type == ClientPayInstallmentSubData::class) {
                    $invoiceItemData = ClientPayInstallmentSubData::with('parameterValue')->find($invoiceItem->invoiceable_id);
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

                $invoiceItemsData[] = [
                    'description' =>  "sconto",
                    'priceAfterDiscount' => $client->discount_type == 0 ? $invoiceTotal * ($invoice->discount_amount / 100) : $invoice->discount_amount,
                    'additionalTaxPercentage' => 0
                ];

                if($invoice->discount_type == 0){
                    $invoiceTotal -= $invoiceTotal * ($invoice->discount_amount / 100);
                }else{
                    $invoiceTotal -= $invoice->discount_amount;
                }

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

    }*/

    public function index(Request $request){
        if($request->type == 'pdf'){
            return $this->generateInvoicePdf($this->getInvoiceData($request));
        } elseif($request->type == 'csv'){
            return $this->generateInvoiceExcel($this->getInvoiceData($request));
        }
    }

    private function getInvoiceData(Request $request){
        $invoice = Invoice::findOrFail($request->invoiceIds[0]);

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

        foreach ($invoiceItems as $invoiceItem) {
            $invoiceItemData = match ($invoiceItem->invoiceable_type) {
                Task::class => Task::with('serviceCategory')->find($invoiceItem->invoiceable_id),
                ClientPayInstallment::class => ClientPayInstallment::with('parameterValue')->find($invoiceItem->invoiceable_id),
                ClientPayInstallmentSubData::class => ClientPayInstallmentSubData::with('parameterValue')->find($invoiceItem->invoiceable_id),
                default => null
            };


            $description = $invoiceItem->invoiceable_type == Task::class
                ? $invoiceItemData->serviceCategory->name
                : $invoiceItemData->parameterValue?->description ?? $invoiceItem->description;


            $invoiceItemsData[] = [
                'description' => $description,
                'priceAfterDiscount' => $invoiceItem->price_after_discount,
                'additionalTaxPercentage' => 22
            ];

            $totalTax += $invoiceItem->price_after_discount * 0.22;
            $invoiceTotal += $invoiceItem->price_after_discount;

            if ($invoiceItem->invoiceable_type == Task::class && $invoiceItemData->serviceCategory->extra_is_pricable) {
                $invoiceItemsData[] = [
                    'description' => $invoiceItemData->serviceCategory->extra_price_description,
                    'priceAfterDiscount' => $invoiceItemData->serviceCategory->extra_price,
                    'additionalTaxPercentage' => 0
                ];

                $invoiceTotal += $invoiceItemData->serviceCategory->extra_price;
            }
        }

        $client = Client::find($invoice->client_id);

        if ($client->total_tax > 0) {
            $invoiceItemsData[] = [
                'description' => $client->total_tax_description ?? '',
                'priceAfterDiscount' => $invoiceTotal * ($client->total_tax / 100),
                'additionalTaxPercentage' => 0
            ];
            $invoiceTotal += $invoiceTotal * ($client->total_tax / 100);
        }

        $clientAddressFormatted = ClientAddress::where('client_id', $client->id)->first()?->address ?? "";
        $clientBankAccountFormatted = ClientBankAccount::where('client_id', $client->id)->first()?->iban ?? "";

        if ($invoice->discount_amount > 0) {
            $discountValue = $invoice->discount_type == 0
                ? $invoiceTotal * ($invoice->discount_amount / 100)
                : $invoice->discount_amount;

            $invoiceItemsData[] = [
                'description' => "sconto",
                'priceAfterDiscount' => $discountValue,
                'additionalTaxPercentage' => 0
            ];

            $invoiceTotal -= $discountValue;
        }

        $paymentMethod = ParameterValue::find($invoice->payment_type_id ?? null);

        return [
            'invoice' => $invoice,
            'invoiceItems' => $invoiceItemsData,
            'invoiceTotalTax' => $totalTax,
            'invoiceTotal' => $invoiceTotal,
            'invoiceTotalWithTax' => $invoiceTotal + $totalTax,
            'client' => $client,
            'clientAddress' => $clientAddressFormatted,
            'clientBankAccount' => $clientBankAccountFormatted,
            'paymentMethod' => $paymentMethod->parameter_value ?? "",
        ];

    }

    private function generateInvoicePdf(array $data)
    {
        $pdf = PDF::loadView('invoice_pdf_report', $data);

        $fileName = 'invoice_' . $data['invoice']->id . '.pdf';
        $path = 'exportedInvoices/' . $fileName;

        Storage::disk('public')->put($path, $pdf->output());

        $url = asset('storage/' . $path);

        return response()->json(['path' => env('APP_URL') . $url]);
    }

    public function generateInvoiceExcel($data)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Define headers
        $headers = ['Cliente', 'Descrizione', 'Prezzo unitario', 'Quantità', 'Prezzo Totale', 'Data prestazione'];

        // Fill headers
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Style headers
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:F1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // Fill rows
        $row = 2;

        foreach ($data['invoiceItems'] as $entry) {
            $sheet
                ->setCellValue('A' . $row, $data['client']->ragione_sociale ?? '')
                ->setCellValue('B' . $row, $entry['description'] ?? '')
                ->setCellValue('C' . $row, $entry['priceAfterDiscount'] ?? 0)
                ->setCellValue('D' . $row, $entry['quantita'] ?? 1)
                ->setCellValue('E' . $row, ($entry['priceAfterDiscount'] ?? 0) * ($entry['quantita'] ?? 1))
                ->setCellValue('F' . $row, Carbon::parse($data['invoice']->created_at)->format('d/m/Y'));
            $row++;
        }

        // Apply borders and autosize
        $sheet->getStyle('A1:F' . ($row - 1))
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach (range('A', 'F') as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $sheet->setAutoFilter('A1:F1');

        // Write to memory and store
        $fileName = 'user_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
        $filePath = 'exportedInvoices/' . $fileName;

        ob_start();
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        $excelOutput = ob_get_clean();

        Storage::disk('public')->put($filePath, $excelOutput);

        $url = asset('storage/' . $filePath);

        return response()->json([
            'path' => env('APP_URL') . parse_url($url, PHP_URL_PATH),
        ]);
    }


}
