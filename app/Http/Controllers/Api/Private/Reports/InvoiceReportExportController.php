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

    public function index(Request $request){
        if($request->type == 'pdf'){
            return $this->generateInvoicePdf($this->getInvoiceData($request));
        } elseif($request->type == 'csv'){
            return $this->generateInvoiceExcel($this->getInvoiceData($request));
        }elseif($request->type == 'xml'){
            return $this->generateInvoiceXml($this->getInvoiceData($request));
        }
    }

    private function getInvoiceData(Request $request){
        $invoice = Invoice::findOrFail($request->invoiceIds[0]);

        $invoiceItems = DB::table('invoice_details')
            ->where('invoice_details.invoice_id', $invoice->id)
            ->select([
                'invoice_details.price',
                'invoice_details.price_after_discount',
                'invoice_details.invoiceable_id',
                'invoice_details.invoiceable_type',
                'invoice_details.description'
            ])->get();

        $invoiceItemsData = [];
        //$totalTax = 0;
        $invoiceTotalToCalcTax = 0;
        $invoiceTotal = 0;

        $invoiceStartAt = Carbon::parse($invoice->created_at)->format('d/m/Y');

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

            $invoiceStartAt = $invoiceItem->invoiceable_type == ClientPayInstallment::class
                ? Carbon::parse(ClientPayInstallment::find($invoiceItem->invoiceable_id)->start_at)->format('d/m/Y')
                : $invoiceStartAt;

            if($invoiceItem->description != null){
                $description = $invoiceItem->description;
            }


            $invoiceItemsData[] = [
                'description' => $description,
                'price' => $invoiceItem->price,
                'priceAfterDiscount' => $invoiceItem->price_after_discount,
                'additionalTaxPercentage' => 22
            ];

            //$totalTax += $invoiceItem->price_after_discount * 0.22;
            $invoiceTotal += $invoiceItem->price_after_discount;
            $invoiceTotalToCalcTax += $invoiceItem->price_after_discount;

            if ($invoiceItem->invoiceable_type == Task::class && $invoiceItemData->serviceCategory->extra_is_pricable) {
                $invoiceItemsData[] = [
                    'description' => $invoiceItemData->serviceCategory->extra_price_description,
                    'price' => $invoiceItem->price == 0 ? $invoiceItemData->serviceCategory->extra_price : $invoiceItem->price,
                    'priceAfterDiscount' => $invoiceItem->price_after_discount == 0 ? $invoiceItemData->serviceCategory->extra_price : $invoiceItem->price,
                    'additionalTaxPercentage' => 0
                ];

                $invoiceTotal += $invoiceItemData->serviceCategory->extra_price;
            }
        }

        $client = Client::find($invoice->client_id);

        if ($client->total_tax > 0) {
            $invoiceItemsData[] = [
                'description' => $client->total_tax_description ?? '',
                'price' => $invoiceTotal * ($client->total_tax / 100),
                'priceAfterDiscount' => $invoiceTotal * ($client->total_tax / 100),
                'additionalTaxPercentage' => 22
            ];

            //$totalTax += ($invoiceTotal * ($client->total_tax / 100) * 0.22);

            $invoiceTotal += $invoiceTotal * ($client->total_tax / 100);
            $invoiceTotalToCalcTax += $invoiceTotal * ($client->total_tax / 100);

        }

        $clientAddressFormatted = ClientAddress::where('client_id', $client->id)->first()?->address ?? "";
        $clientBankAccountFormatted = ClientBankAccount::where('client_id', $client->id)->first()?->iban ?? "";

        if ($invoice->discount_amount > 0) {
            $discountValue = $invoice->discount_type == 0
                ? $invoiceTotal * ($invoice->discount_amount / 100)
                : $invoice->discount_amount;

            $invoiceItemsData[] = [
                'description' => "sconto",
                'price' => $discountValue,
                'priceAfterDiscount' => $discountValue,
                'additionalTaxPercentage' => 0
            ];

            $invoiceTotal -= $discountValue;

            $invoiceTotalToCalcTax -= $discountValue;

        }

        $paymentMethod = ParameterValue::find($invoice->payment_type_id ?? null);

        $invoiceTotalToCalcTax = $invoiceTotalToCalcTax * 0.22;

        return [
            'invoice' => $invoice,
            'invoiceStartAt' => $invoiceStartAt,
            'invoiceItems' => $invoiceItemsData,
            'invoiceTotalTax' => $invoiceTotalToCalcTax,
            'invoiceTotal' => $invoiceTotal,
            'invoiceTotalWithTax' => $invoiceTotal + $invoiceTotalToCalcTax,
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

    public function generateInvoiceXml(array $data)
    {
    function safeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    // Create the root element
    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><RibaCBI></RibaCBI>');

    // === Record 14: File Header ===
    $record14 = $xml->addChild('Record');
    $record14->addAttribute('type', '14');

    $iban = $data['client']['bank_iban'] ?? 'IT00X0000000000000000000000';
    $senderAbi = substr($iban, 5, 5); // ABI from IBAN

    $record14->addChild('SenderABI', safeXml($senderAbi));
    $record14->addChild('CreationDate', now()->format('dmy'));
    $record14->addChild('FlowDate', now()->format('dmy'));
    $record14->addChild('Version', 'E');

    // === Record 20: Client/Biller Info ===
    $record20 = $xml->addChild('Record');
    $record20->addAttribute('type', '20');

    $clientVat = $data['client']['iva'] ?? null;
    $clientFiscal = $data['client']['cf'] ?? null;
    $clientName = $data['client']['ragione_sociale'] ?? 'CLIENTE SCONOSCIUTO';

    $record20->addChild('ClientCode', safeXml($clientVat ?? $clientFiscal ?? 'ND'));
    $record20->addChild('FiscalCode', safeXml($clientFiscal ?? 'ND'));
    $record20->addChild('ClientName', safeXml($clientName));

    // === Record 30: Payment Entries ===
    $totalAmount = 0;
    $transactionCount = 0;

    foreach ($data['invoiceItems'] as $item) {
        $record30 = $xml->addChild('Record');
        $record30->addAttribute('type', '30');

        $dueDate = Carbon::createFromFormat('d/m/Y', $data['invoiceStartAt'] ?? now()->format('d/m/Y'));

        $record30->addChild('DueDate', $dueDate->format('dmy'));

        $amountInCents = number_format(($item['priceAfterDiscount'] ?? 0) * 100, 0, '', '');
        $record30->addChild('Amount', $amountInCents);

        $record30->addChild('PayerName', safeXml($clientName));
        $record30->addChild('PayerIBAN', safeXml($iban));
        $record30->addChild('Description', safeXml($item['description'] ?? 'Pagamento'));

        $totalAmount += $item['priceAfterDiscount'] ?? 0;
        $transactionCount++;
    }

    // === Record 40: Summary ===
    $record40 = $xml->addChild('Record');
    $record40->addAttribute('type', '40');
    $record40->addChild('TotalRecords', $transactionCount);
    $record40->addChild('TotalAmount', number_format($totalAmount * 100, 0, '', ''));

    // === Record 50: File Footer ===
    $record50 = $xml->addChild('Record');
    $record50->addAttribute('type', '50');
    $record50->addChild('TotalLines', $xml->count() + 1); // Includes this record

    // === Return as downloadable XML file ===
    return response($xml->asXML(), 200)
        ->header('Content-Type', 'application/xml')
        ->header('Content-Disposition', 'attachment; filename="riba_cbi.xml"');

    }


}
