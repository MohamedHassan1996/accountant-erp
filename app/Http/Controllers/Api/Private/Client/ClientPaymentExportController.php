<?php
namespace App\Http\Controllers\Api\Private\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminTask\AllAdminTaskResource;
use App\Services\Task\ExportTaskService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\DB;


class ClientPaymentExportController extends Controller
{
    protected $taskService;


    public function __construct(ExportTaskService $taskService)
    {
        $this->taskService = $taskService;
    }

public function index(Request $request)
{
    $clients = DB::table('clients')
        ->whereNull('deleted_at')
        ->orderBy('ragione_sociale')
        ->get();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Headers
    $headers = ['Cliente', 'Start Date', 'Descrizione', 'Totale'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    $sheet->getStyle('A1:D1')->getFont()->setBold(true);
    $sheet->getStyle('A1:D1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $row = 2;

    foreach ($clients as $client) {

        // Get installments per client
        $installments = DB::table('client_pay_installments as cpi')
            ->where('cpi.client_id', $client->id)
            ->whereNull('cpi.deleted_at')
            ->leftJoin('parameter_values as pv', 'pv.id', '=', 'cpi.parameter_value_id')
            ->select(
                'cpi.id',
                'cpi.start_at',
                'pv.description as description',
                'cpi.amount'
            )
            ->get();

        foreach ($installments as $installment) {

            // Main installment row
            $sheet->setCellValue('A' . $row, $client->ragione_sociale);
            $sheet->setCellValue('B' . $row, $installment->start_at);
            $sheet->setCellValue('C' . $row, $installment->description);
            $sheet->setCellValue('D' . $row, $installment->amount);
            $row++;

            // Sub installments (each as separated row)
            $subs = DB::table('client_pay_installment_sub_data as sub')
                ->where('sub.client_pay_installment_id', $installment->id)
                ->whereNull('sub.deleted_at')
                ->leftJoin('parameter_values as pv', 'pv.id', '=', 'sub.parameter_value_id')
                ->select(
                    'pv.description as description',
                    'sub.price'
                )
                ->get();

            foreach ($subs as $sub) {
                $sheet->setCellValue('A' . $row, $client->ragione_sociale);
                $sheet->setCellValue('B' . $row, null);
                $sheet->setCellValue('C' . $row, $sub->description);
                $sheet->setCellValue('D' . $row, $sub->price);
                $row++;
            }
        }
    }

    // Borders & styling
    $sheet->getStyle('A1:D' . ($row - 1))
        ->getBorders()
        ->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);

    foreach (range('A', 'D') as $colLetter) {
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }

    $sheet->setAutoFilter('A1:D1');
    $sheet->setTitle('Dettaglio');

    // ===================== Sheet 2: Proposta =====================
    $proposta = $spreadsheet->createSheet();
    $proposta->setTitle('Proposta');

    // Get parameter_values where parameter_order = 8 or 9
    $paramValues = DB::table('parameter_values as pv')
        ->leftJoin('parameters as p', 'p.id', '=', 'pv.parameter_id')
        ->whereIn('p.parameter_order', [8, 9])
        ->whereNull('pv.deleted_at')
        ->select('pv.id', 'pv.parameter_value')
        ->orderBy('p.parameter_order')
        ->orderBy('pv.parameter_value')
        ->get();

    // Build column map: param_value_id => column index (starting from 2 = col B)
    $colMap = [];
    $colIndex = 2;
    foreach ($paramValues as $pv) {
        $colMap[$pv->id] = $colIndex++;
    }
    $totalColIndex = $colIndex; // last column = Total

    // Header row
    $proposta->setCellValueByColumnAndRow(1, 1, 'Cliente');
    foreach ($paramValues as $pv) {
        $proposta->setCellValueByColumnAndRow($colMap[$pv->id], 1, $pv->parameter_value);
    }
    $proposta->setCellValueByColumnAndRow($totalColIndex, 1, 'Totale');

    $lastHeaderCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColIndex);
    $proposta->getStyle('A1:' . $lastHeaderCol . '1')->getFont()->setBold(true);
    $proposta->getStyle('A1:' . $lastHeaderCol . '1')
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Get installments grouped by client and parameter_value (only order 8 or 9)
    $installmentData = DB::table('client_pay_installments as cpi')
        ->whereNull('cpi.deleted_at')
        ->join('parameter_values as pv', 'pv.id', '=', 'cpi.parameter_value_id')
        ->join('parameters as p', 'p.id', '=', 'pv.parameter_id')
        ->whereIn('p.parameter_order', [8, 9])
        ->select('cpi.client_id', 'pv.id as pv_id', DB::raw('SUM(cpi.amount) as total'))
        ->groupBy('cpi.client_id', 'pv.id')
        ->get()
        ->groupBy('client_id');

    $propostaRow = 2;
    foreach ($clients as $client) {
        $proposta->setCellValueByColumnAndRow(1, $propostaRow, $client->ragione_sociale);

        $rowTotal = 0;
        $clientData = $installmentData->get($client->id, collect());

        foreach ($clientData as $item) {
            if (isset($colMap[$item->pv_id])) {
                $proposta->setCellValueByColumnAndRow($colMap[$item->pv_id], $propostaRow, $item->total);
                $rowTotal += $item->total;
            }
        }

        $proposta->setCellValueByColumnAndRow($totalColIndex, $propostaRow, $rowTotal);
        $propostaRow++;
    }

    // Styling Proposta
    $proposta->getStyle('A1:' . $lastHeaderCol . ($propostaRow - 1))
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    for ($i = 1; $i <= $totalColIndex; $i++) {
        $proposta->getColumnDimensionByColumn($i)->setAutoSize(true);
    }

    $proposta->setAutoFilter('A1:' . $lastHeaderCol . '1');

    // ===================== Sheet 3: Macro_Servizi =====================
    $macro = $spreadsheet->createSheet();
    $macro->setTitle('Macro_Servizi');

    // Categories = parameter_values where parameter_order = 12
    $categories = DB::table('parameter_values')
        ->whereNull('deleted_at')
        ->where('parameter_order', 12)
        ->select('id', 'parameter_value')
        ->orderBy('parameter_value')
        ->get();

    // Build category column map: category parameter_value (string) => col index
    $catColMap = [];
    $catColIndex = 2;
    foreach ($categories as $cat) {
        $catColMap[$cat->parameter_value] = $catColIndex++;
    }
    $macroTotalCol = $catColIndex;

    // Header row
    $macro->setCellValueByColumnAndRow(1, 1, 'Cliente');
    foreach ($categories as $cat) {
        $macro->setCellValueByColumnAndRow($catColMap[$cat->parameter_value], 1, $cat->parameter_value);
    }
    $macro->setCellValueByColumnAndRow($macroTotalCol, 1, 'Totale');

    $macroLastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($macroTotalCol);
    $macro->getStyle('A1:' . $macroLastCol . '1')->getFont()->setBold(true);
    $macro->getStyle('A1:' . $macroLastCol . '1')
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Get installments grouped by client and category
    // pv.description2 stores the category id (parameter_order=12) as string
    $macroData = DB::table('client_pay_installments as cpi')
        ->whereNull('cpi.deleted_at')
        ->join('parameter_values as pv', 'pv.id', '=', 'cpi.parameter_value_id')
        ->join('parameters as p', 'p.id', '=', 'pv.parameter_id')
        ->whereIn('p.parameter_order', [8, 9])
        ->whereNotNull('pv.description2')
        ->join('parameter_values as cat', DB::raw('CAST(pv.description2 AS UNSIGNED)'), '=', 'cat.id')
        ->where('cat.parameter_order', 12)
        ->select(
            'cpi.client_id',
            'cat.parameter_value as category',
            DB::raw('SUM(cpi.amount) as total')
        )
        ->groupBy('cpi.client_id', 'cat.parameter_value')
        ->get()
        ->groupBy('client_id');

    $macroRow = 2;
    foreach ($clients as $client) {
        $macro->setCellValueByColumnAndRow(1, $macroRow, $client->ragione_sociale);

        $rowTotal = 0;
        $clientCats = $macroData->get($client->id, collect());

        foreach ($clientCats as $item) {
            if (isset($catColMap[$item->category])) {
                $macro->setCellValueByColumnAndRow($catColMap[$item->category], $macroRow, $item->total);
                $rowTotal += $item->total;
            }
        }

        $macro->setCellValueByColumnAndRow($macroTotalCol, $macroRow, $rowTotal);
        $macroRow++;
    }

    // Styling Macro_Servizi
    $macro->getStyle('A1:' . $macroLastCol . ($macroRow - 1))
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    for ($i = 1; $i <= $macroTotalCol; $i++) {
        $macro->getColumnDimensionByColumn($i)->setAutoSize(true);
    }

    $macro->setAutoFilter('A1:' . $macroLastCol . '1');

    // ===================== Sheet 4: Riepilogo =====================
    $riepilogo = $spreadsheet->createSheet();
    $riepilogo->setTitle('Riepilogo');

    $riepilogo->setCellValue('A1', 'Macro Servizi');
    $riepilogo->setCellValue('B1', 'Totale');
    $riepilogo->getStyle('A1:B1')->getFont()->setBold(true);
    $riepilogo->getStyle('A1:B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Sum all installments grouped by category
    $riepilogoData = DB::table('client_pay_installments as cpi')
        ->whereNull('cpi.deleted_at')
        ->join('parameter_values as pv', 'pv.id', '=', 'cpi.parameter_value_id')
        ->join('parameters as p', 'p.id', '=', 'pv.parameter_id')
        ->whereIn('p.parameter_order', [8, 9])
        ->whereNotNull('pv.description2')
        ->join('parameter_values as cat', DB::raw('CAST(pv.description2 AS UNSIGNED)'), '=', 'cat.id')
        ->where('cat.parameter_order', 12)
        ->select('cat.parameter_value as category', DB::raw('SUM(cpi.amount) as total'))
        ->groupBy('cat.parameter_value')
        ->orderBy('cat.parameter_value')
        ->get();

    $rRow = 2;
    foreach ($riepilogoData as $item) {
        $riepilogo->setCellValue('A' . $rRow, $item->category);
        $riepilogo->setCellValue('B' . $rRow, $item->total);
        $rRow++;
    }

    $riepilogo->getStyle('A1:B' . ($rRow - 1))
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $riepilogo->getColumnDimension('A')->setAutoSize(true);
    $riepilogo->getColumnDimension('B')->setAutoSize(true);

    // Save Excel
    $fileName = 'client_installments_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
    $filePath = 'client_installments_exports/' . $fileName;

    ob_start();
    (new Xlsx($spreadsheet))->save('php://output');
    $excelOutput = ob_get_clean();

    Storage::disk('public')->put($filePath, $excelOutput);

    return response()->json([
        'path' => Storage::disk('public')->url($filePath),
    ]);
}

}
