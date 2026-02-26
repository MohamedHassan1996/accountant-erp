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

    /*private function getInvoiceData(Request $request){
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

            // Get service code from Task's ServiceCategory
            $serviceCode = '..'; // Default value
            if ($invoiceItem->invoiceable_type == Task::class && $invoiceItemData && $invoiceItemData->serviceCategory) {
                $serviceCode = $invoiceItemData->serviceCategory->code ?? '..';
            }

            $invoiceItemsData[] = [
                'description' => $description,
                'price' => $invoiceItem->price,
                'priceAfterDiscount' => $invoiceItem->price_after_discount,
                'additionalTaxPercentage' => 22,
                'serviceCode' => $serviceCode
            ];

            //$totalTax += $invoiceItem->price_after_discount * 0.22;
            $invoiceTotal += $invoiceItem->price_after_discount;
            $invoiceTotalToCalcTax += $invoiceItem->price_after_discount;

            if ($invoiceItem->invoiceable_type == Task::class && $invoiceItemData->serviceCategory->extra_is_pricable) {
                $invoiceItemsData[] = [
                    'description' => $invoiceItemData->serviceCategory->extra_price_description,
                    'price' => $invoiceItem->price == 0 ? $invoiceItemData->serviceCategory->extra_price : $invoiceItem->price,
                    'priceAfterDiscount' => $invoiceItem->price_after_discount == 0 ? $invoiceItemData->serviceCategory->extra_price : $invoiceItem->price,
                    'additionalTaxPercentage' => 0,
                    'serviceCode' => $invoiceItemData->serviceCategory->code ?? '..'
                ];

                $invoiceTotal += $invoiceItemData->serviceCategory->extra_price;
            }
        }

        $client = Client::find($invoice->client_id);

                $clientAddressData = ClientAddress::where('client_id', $client->id)->first();

        if ($client->total_tax > 0) {
            $invoiceItemsData[] = [
                'description' => $client->total_tax_description ?? '',
                'price' => $invoiceTotal * ($client->total_tax / 100),
                'priceAfterDiscount' => $invoiceTotal * ($client->total_tax / 100),
                'additionalTaxPercentage' => 22,
                'serviceCode' => '..'
            ];

            //$totalTax += ($invoiceTotal * ($client->total_tax / 100) * 0.22);

            $invoiceTotal += $invoiceTotal * ($client->total_tax / 100);
            $invoiceTotalToCalcTax += $invoiceTotal * ($client->total_tax / 100);

        }

        $clientAddressFormatted = ClientAddress::where('client_id', $client->id)->first()?->address ?? "";
        $clientBankAccount = ClientBankAccount::where('client_id', $client->id)->where('is_main', 1)->first();

        $clientBankAccountFormatted = [];

        if($clientBankAccount != null){
            $clientBankAccountFormatted = [
                'iban' => $clientBankAccount->iban??"",
                'abi' => $clientBankAccount->abi??"",
                'cab' => $clientBankAccount->cab??""
            ];
        }

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
            'clientAddressData' => $clientAddressData->toArray(),
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

    }*/

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

            // Get service code from Task's ServiceCategory
            $serviceCode = '..'; // Default value
            if ($invoiceItem->invoiceable_type == Task::class && $invoiceItemData && $invoiceItemData->serviceCategory) {
                $serviceCode = $invoiceItemData->serviceCategory->code ?? '..';
            }

            $invoiceItemsData[] = [
                'description' => $description,
                'price' => $invoiceItem->price,
                'priceAfterDiscount' => $invoiceItem->price_after_discount,
                'additionalTaxPercentage' => 22,
                'serviceCode' => $serviceCode
            ];

            //$totalTax += $invoiceItem->price_after_discount * 0.22;
            $invoiceTotal += $invoiceItem->price_after_discount;
            $invoiceTotalToCalcTax += $invoiceItem->price_after_discount;

            if ($invoiceItem->invoiceable_type == Task::class && $invoiceItemData->serviceCategory->extra_is_pricable) {
                $invoiceItemsData[] = [
                    'description' => $invoiceItemData->serviceCategory->extra_price_description,
                    'price' => $invoiceItem->price == 0 ? $invoiceItemData->serviceCategory->extra_price : $invoiceItem->price,
                    'priceAfterDiscount' => $invoiceItem->price_after_discount == 0 ? $invoiceItemData->serviceCategory->extra_price : $invoiceItem->price,
                    'additionalTaxPercentage' => 0,
                    'serviceCode' => $invoiceItemData->serviceCategory->code ?? '..'
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
                'additionalTaxPercentage' => 22,
                'serviceCode' => '..'
            ];

            //$totalTax += ($invoiceTotal * ($client->total_tax / 100) * 0.22);

            $invoiceTotal += $invoiceTotal * ($client->total_tax / 100);



            $invoiceTotalToCalcTax += $invoiceTotalToCalcTax * ($client->total_tax / 100);




        }



        $clientAddressFormatted = ClientAddress::where('client_id', $client->id)->first()?->address ?? "";

        $clientBankAccount = ClientBankAccount::where('client_id', $client->id)->where('is_main', 1)->first();

        $clientBankAccountFormatted = [];

        if($clientBankAccount != null){
            $clientBankAccountFormatted = [
                'iban' => $clientBankAccount->iban??"",
                'abi' => $clientBankAccount->abi??"",
                'cab' => $clientBankAccount->cab??"",
                'bankName' => $clientBankAccount->banca
            ];
        }

        $clientAddressData = ClientAddress::where('client_id', $client->id)->first();


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

        $bankAccount = null;

        if($invoice->bank_account_id){
            $bankAccount = ParameterValue::where('id',$invoice->bank_account_id)->first();
        }else{
            $bankAccount = ParameterValue::where('parameter_order', 7)->where('is_default', 1)->first();
        }



        return [
            'invoice' => $invoice,
            'clientAddressData' => $clientAddressData->toArray(),
            'invoiceStartAt' => $invoiceStartAt,
            'invoiceItems' => $invoiceItemsData,
            'invoiceTotalTax' => $invoiceTotalToCalcTax,
            'invoiceTotal' => $invoiceTotal,
            'invoiceTotalWithTax' => $invoiceTotal + $invoiceTotalToCalcTax,
            'client' => $client,
            'clientAddress' => $clientAddressFormatted,
            'clientBankAccount' => $clientBankAccountFormatted,
            'paymentMethod' => $paymentMethod->code ?? "",
            'bankAccount' => [
                'iban' => $bankAccount->parameter_value??'',
                'abi' => $bankAccount->description2??'',
                'cab' => $bankAccount->description3??'',
                'bankName' => $bankAccount->description??''
            ],
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
    $safe = fn($v) => htmlspecialchars(trim((string)$v), ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $parseDate = function ($value) {
        try {
            if (!$value) return now()->format('Y-m-d');
            $clean = trim(explode(' ', (string)$value)[0]);
            if (str_contains($clean, '/')) {
                return \Carbon\Carbon::createFromFormat('d/m/Y', $clean)->format('Y-m-d');
            }
            return \Carbon\Carbon::parse($clean)->format('Y-m-d');
        } catch (\Exception $e) {
            return now()->format('Y-m-d');
        }
    };

    $usePassepartout = !empty($data['client']['sdi_code']) && $data['client']['sdi_code'] !== '0000000';

    /* ================= 1. بناء الهيكل الأساسي بدون Namespaces مؤقتاً ================= */
    // نبدأ بـ Root مؤقت بدون Namespace لتجنب مشكلة توريث الـ prefix p: أو ظهور xmlns=""
    $xml = new \SimpleXMLElement(
        '<?xml version="1.0" encoding="windows-1252"?>' .
        '<?xml-stylesheet type="text/xsl" href="fatturaordinaria_v1.2.xsl"?>' .
        '<FatturaElettronica versione="FPR12"></FatturaElettronica>'
    );

    /* ================= HEADER ================= */
    $header = $xml->addChild('FatturaElettronicaHeader');

    /* --- DatiTrasmissione --- */
    $trasm = $header->addChild('DatiTrasmissione');
    $idTras = $trasm->addChild('IdTrasmittente');
    if ($usePassepartout) {
        $idTras->addChild('IdPaese', 'SM');
        $idTras->addChild('IdCodice', '03473');
        $trasm->addChild('CodiceDestinatario', $safe($data['client']['sdi']));
    } else {
        $idTras->addChild('IdPaese', 'IT');
        $idTras->addChild('IdCodice', '00987920196');
        $trasm->addChild('CodiceDestinatario', $safe($data['client']['sdi'] ?? '0000000'));
    }

    DB::transaction(function () use (&$invoiceNewNumber) {
        // Get the parameter value from parameter_order = 13
        $parameterValue = ParameterValue::where('parameter_order', 13)->first();
        $parameterNumber = $parameterValue?->parameter_value ?? '1/57';

        // Get the latest invoice xml number from database
        $latestInvoice = Invoice::lockForUpdate()
            ->whereNotNull('invoice_xml_number')
            ->latest('id')
            ->first();
        $lastDbNumber = $latestInvoice?->invoice_xml_number ?? '1/0';

        // Compare the numbers (second part after /)
        $parameterParts = explode('/', $parameterNumber);
        $dbParts = explode('/', $lastDbNumber);

        $parameterNum = (int) ($parameterParts[1] ?? 0);
        $dbNum = (int) ($dbParts[1] ?? 0);

        // Use parameter value if it's higher, otherwise increment db value
        if ($parameterNum > $dbNum) {
            $invoiceNewNumber = $parameterNumber;
        } else {
            $dbParts[1] = $dbNum + 1;
            $invoiceNewNumber = implode('/', $dbParts);
        }
    });

    $trasm->addChild('ProgressivoInvio', $invoiceNewNumber);
    $trasm->addChild('FormatoTrasmissione', 'FPR12');

    /* --- CedentePrestatore --- */
    $ced = $header->addChild('CedentePrestatore');
    $datiCed = $ced->addChild('DatiAnagrafici');
    $ivaCed = $datiCed->addChild('IdFiscaleIVA');
    $ivaCed->addChild('IdPaese', 'IT');
    $ivaCed->addChild('IdCodice', '00987920196');
    $datiCed->addChild('CodiceFiscale', '00987920196');
    $anaCed = $datiCed->addChild('Anagrafica');
    $anaCed->addChild('Denominazione', 'ELABORAZIONI SRL');
    $datiCed->addChild('RegimeFiscale', 'RF01');

    $sedeCed = $ced->addChild('Sede');
    $sedeCed->addChild('Indirizzo', 'VIA STAZIONE 9/B');
    $sedeCed->addChild('CAP', '26013');
    $sedeCed->addChild('Comune', 'CREMA');
    $sedeCed->addChild('Provincia', 'CR');
    $sedeCed->addChild('Nazione', 'IT');

    $rea = $ced->addChild('IscrizioneREA');
    $rea->addChild('Ufficio', 'CR');
    $rea->addChild('NumeroREA', '126442');
    $rea->addChild('CapitaleSociale', '10000.00');
    $rea->addChild('SocioUnico', 'SM');
    $rea->addChild('StatoLiquidazione', 'LN');

    $contatti = $ced->addChild('Contatti');
    $contatti->addChild('Telefono', '037386998');
    $contatti->addChild('Email', 'info@studiocrottibignami.it');

    /* --- CessionarioCommittente --- */
    $cess = $header->addChild('CessionarioCommittente');
    $datiCess = $cess->addChild('DatiAnagrafici');
    if (!empty($data['client']['iva'])) {
        $ivaCess = $datiCess->addChild('IdFiscaleIVA');
        $ivaCess->addChild('IdPaese', 'IT');
        $ivaCess->addChild('IdCodice', $safe($data['client']['iva']));
    }
    if (!empty($data['client']['cf'])) {
        $datiCess->addChild('CodiceFiscale', $safe($data['client']['cf']));
    }
    $anaCess = $datiCess->addChild('Anagrafica');
    $anaCess->addChild('Denominazione', $safe($data['client']['ragione_sociale']));

    $provRaw = $data['clientAddressData']['province'] ?? '';
    $prov = strtoupper(substr(trim($provRaw), 0, 2)) ?: 'XX';
    $sedeCess = $cess->addChild('Sede');
    $sedeCess->addChild('Indirizzo', $safe($data['clientAddressData']['address']));
    $sedeCess->addChild('CAP', $safe($data['clientAddressData']['cap'] ?? '00000'));
    $sedeCess->addChild('Comune', $safe($data['clientAddressData']['city']));
    $sedeCess->addChild('Provincia', $prov);
    $sedeCess->addChild('Nazione', 'IT');

    /* --- Terzo Intermediario --- */
    $terzo = $header->addChild('TerzoIntermediarioOSoggettoEmittente');
    $datiTerzo = $terzo->addChild('DatiAnagrafici');
    $ivaTerzo = $datiTerzo->addChild('IdFiscaleIVA');
    $ivaTerzo->addChild('IdPaese', 'SM');
    $ivaTerzo->addChild('IdCodice', '03473');
    $anaTerzo = $datiTerzo->addChild('Anagrafica');
    $anaTerzo->addChild('Denominazione', 'Passepartout S.p.A');

    $header->addChild('SoggettoEmittente', 'TZ');

    /* ================= BODY ================= */
    $body = $xml->addChild('FatturaElettronicaBody');
    $gen = $body->addChild('DatiGenerali');
    $doc = $gen->addChild('DatiGeneraliDocumento');
    $doc->addChild('TipoDocumento', 'TD01');
    $doc->addChild('Divisa', 'EUR');
    $doc->addChild('Data', $parseDate($data['invoiceStartAt']));

    // Extract the second part of invoiceNewNumber (e.g., '1/57' -> '57')
    $invoiceNumberParts = explode('/', $invoiceNewNumber ?? '');
    $invoiceNumero = $invoiceNumberParts[1] ?? $data['invoice']['number'];
    $doc->addChild('Numero', $safe($invoiceNumero));

    $doc->addChild('ImportoTotaleDocumento', number_format((float)$data['invoiceTotalWithTax'], 2, '.', ''));

    // Causale من أول بند
    foreach ($data['invoiceItems'] as $item) {
        if ((float)($item['priceAfterDiscount'] ?? 0) > 0 && !empty($item['description'])) {
            $doc->addChild('Causale', $safe($item['description']));
            break;
        }
    }

    $beni = $body->addChild('DatiBeniServizi');
    $line = 1;
    foreach (array_values($data['invoiceItems']) as $item) {
        if ((float)($item['priceAfterDiscount'] ?? 0) <= 0) continue;

        $det = $beni->addChild('DettaglioLinee');
        $det->addChild('NumeroLinea', (string)$line);
        $codArt = $det->addChild('CodiceArticolo');
        $codArt->addChild('CodiceTipo', 'PRESTAZIONE');
        $codArt->addChild('CodiceValore', $item['serviceCode'] ?? '..');
        $det->addChild('Descrizione', $safe($item['description'] ?? 'Senza descrizione'));
        $det->addChild('Quantita', '1.00');
        $det->addChild('UnitaMisura', 'NR');
        $det->addChild('PrezzoUnitario', number_format((float)$item['priceAfterDiscount'], 2, '.', ''));
        $det->addChild('PrezzoTotale', number_format((float)$item['priceAfterDiscount'], 2, '.', ''));
        $det->addChild('AliquotaIVA', number_format((float)($item['additionalTaxPercentage'] ?? 22), 2, '.', ''));
        $line++;
    }

    $riep = $beni->addChild('DatiRiepilogo');
    $riep->addChild('AliquotaIVA', '22.00');
    $riep->addChild('ImponibileImporto', number_format((float)$data['invoiceTotal'], 2, '.', ''));
    $riep->addChild('Imposta', number_format((float)$data['invoiceTotalTax'], 2, '.', ''));
    $riep->addChild('EsigibilitaIVA', 'I');

    /* ================= PAGAMENTO ================= */
    $pag = $body->addChild('DatiPagamento');
    $pag->addChild('CondizioniPagamento', 'TP02');
    $detPag = $pag->addChild('DettaglioPagamento');
    $modalita = $data['paymentMethod'];
    $detPag->addChild('ModalitaPagamento', $modalita);
    $detPag->addChild('DataScadenzaPagamento', $parseDate($data['invoice']['end_at'] ?? $data['invoiceStartAt']));
    $detPag->addChild('ImportoPagamento', number_format((float)$data['invoiceTotalWithTax'], 2, '.', ''));

    if ($modalita === 'MP05') {
        $detPag->addChild('IstitutoFinanziario', 'BANCO BPM SPA');
        $detPag->addChild('ABI', '05034');
        $detPag->addChild('CAB', '56760');
        $detPag->addChild('IBAN', 'IT00X0503456760000000000000');
    } elseif ($modalita === 'MP12') {
        $detPag->addChild('IstitutoFinanziario', $data['clientBankAccount']['bankName'] ?? '');
        $detPag->addChild('ABI', $data['clientBankAccount']['abi'] ?? '');
        $detPag->addChild('CAB', $data['clientBankAccount']['cab'] ?? '');
    }

    /* ================= 2. تحويل الهيكل لإضافة p: Namespaces ================= */
    $dom = new \DOMDocument('1.0', 'windows-1252');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());

    $root = $dom->documentElement;

    // إنشاء عنصر جديد يحمل التاج p: والفراغ المسمي الصحيح
    $ns = 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2';
    $newRoot = $dom->createElementNS($ns, 'p:FatturaElettronica');

    // نقل الخصائص والـ Namespaces الأخرى
    $newRoot->setAttribute('versione', $root->getAttribute('versione'));
    $newRoot->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');
    $newRoot->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

    // نقل كافة العناصر الأبناء من الجذر القديم للجديد
    while ($root->hasChildNodes()) {
        $newRoot->appendChild($root->firstChild);
    }

    $dom->replaceChild($newRoot, $root);
    $xmlContent = $dom->saveXML();

    /* ================= SAVE & RETURN ================= */
    $clientIva = $data['client']['iva'] ?? '00000000000';

    // Extract the second part after slash (e.g., '60' from '1/60')
    $invoiceNumberParts = explode('/', $invoiceNewNumber ?? '');
    $invoiceNumberPart = end($invoiceNumberParts); // Get the last part after slash
    $invoiceNumberPart = str_pad($invoiceNumberPart, 5, '0', STR_PAD_LEFT); // Add padding to make it 5 digits

    $fileName = '00987920196' . '_' . $invoiceNumberPart . '.xml';
    $path = 'exportedInvoices/' . $fileName;

    Storage::disk('local')->put($path, $xmlContent);

    // Update invoice with XML number
    Invoice::where('id', $data['invoice']['id'])->update(['invoice_xml_number' => $invoiceNewNumber]);

    // Update parameter value with new number for next invoice
    $parameterValue = ParameterValue::where('parameter_order', 13)->first();
    if ($parameterValue) {
        $parameterValue->parameter_value = $invoiceNewNumber;
        $parameterValue->save();
    }

    return response()->json([
        'data' => [
            'name' => $fileName,
            'content' => mb_convert_encoding($xmlContent, 'UTF-8', 'WINDOWS-1252'),
        ]
    ]);
}

}
