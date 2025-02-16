<?php

namespace App\Http\Controllers\Api\Private\Reports;

use App\Http\Controllers\Controller;
use App\Models\Client\Client;
use App\Models\Invoice\Invoice;
use App\Models\Parameter\ParameterValue;
use App\Models\Task\Task;
use App\Services\Reports\ReportService;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
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

            $tasks = Task::where('invoice_id', $invoice->id)->get();

            $client = Client::find($invoice->client_id);

            $paymentMethod = ParameterValue::find($invoice->payment_type_id ?? null);

            $pdf = PDF::loadView('invoice_pdf_report', [
                'invoice' => $invoice,
                'tasks' => $tasks,
                'client' => $client,
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
