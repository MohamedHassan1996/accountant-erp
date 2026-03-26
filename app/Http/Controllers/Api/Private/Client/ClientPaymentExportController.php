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

    // Get all installments with client info (only those with parameter_id 8 or 9)
    $installments = DB::table('client_pay_installments as cpi')
        ->whereNull('cpi.deleted_at')
        ->join('clients as c', 'c.id', '=', 'cpi.client_id')
        ->whereNull('c.deleted_at')
        ->leftJoin('parameter_values as pv', 'pv.id', '=', 'cpi.parameter_value_id')
        ->whereIn('pv.parameter_id', [8, 9])
        ->select(
            'cpi.id',
            'cpi.start_at',
            'c.ragione_sociale',
            'pv.description as description',
            'cpi.amount'
        )
        ->orderBy('c.ragione_sociale')
        ->orderBy('cpi.start_at')
        ->get();

    foreach ($installments as $installment) {

        // Main installment row
        $sheet->setCellValue('A' . $row, $installment->ragione_sociale);
        $sheet->setCellValue('B' . $row, $installment->start_at ? Carbon::parse($installment->start_at)->format('d/m/Y') : '');
        $sheet->setCellValue('C' . $row, $installment->description);
        $sheet->setCellValue('D' . $row, $installment->amount ?? 0);
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
            $sheet->setCellValue('A' . $row, $installment->ragione_sociale);
            $sheet->setCellValue('B' . $row, $installment->start_at ? Carbon::parse($installment->start_at)->format('d/m/Y') : '');
            $sheet->setCellValue('C' . $row, $sub->description);
            $sheet->setCellValue('D' . $row, $sub->price ?? 0);
            $row++;
        }
    }

    // Add total row
    $sheet->setCellValue('A' . $row, 'TOTALE');
    $sheet->setCellValue('D' . $row, '=SUM(D2:D' . ($row - 1) . ')');
    $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);

    // Borders & styling
    $sheet->getStyle('A1:D' . $row)
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

    // Get parameter_values where parameter_id = 8 or 9 — only those actually used in installments
    $paramValues = DB::table('parameter_values as pv')
        ->whereIn('pv.parameter_id', [8, 9])
        ->whereNull('pv.deleted_at')
        ->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('client_pay_installments as cpi')
                ->whereColumn('cpi.parameter_value_id', 'pv.id')
                ->whereNull('cpi.deleted_at');
        })
        ->select('pv.id', 'pv.parameter_value', 'pv.parameter_id')
        ->orderBy('pv.parameter_id')
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

    // Get installments grouped by client and parameter_value (only parameter_id 8 or 9)
    $installmentData = DB::table('client_pay_installments as cpi')
        ->whereNull('cpi.deleted_at')
        ->join('parameter_values as pv', 'pv.id', '=', 'cpi.parameter_value_id')
        ->whereIn('pv.parameter_id', [8, 9])
        ->leftJoinSub(
            DB::table('client_pay_installment_sub_data')
                ->whereNull('deleted_at')
                ->select('client_pay_installment_id', DB::raw('SUM(COALESCE(price, 0)) as sub_total'))
                ->groupBy('client_pay_installment_id'),
            'sub_totals',
            'sub_totals.client_pay_installment_id', '=', 'cpi.id'
        )
        ->select(
            'cpi.client_id',
            'pv.id as pv_id',
            DB::raw('SUM(COALESCE(cpi.amount, 0) + COALESCE(sub_totals.sub_total, 0)) as total')
        )
        ->groupBy('cpi.client_id', 'pv.id')
        ->get()
        ->groupBy('client_id');

    // Get client names for lookup
    $clients = DB::table('clients')
        ->whereNull('deleted_at')
        ->select('id', 'ragione_sociale')
        ->get()
        ->keyBy('id');

    $propostaRow = 2;

    // Iterate over installmentData directly to ensure we don't miss any clients
    foreach ($installmentData as $clientId => $clientData) {
        $clientName = $clients->get($clientId)->ragione_sociale ?? 'Unknown Client';

        $proposta->setCellValueByColumnAndRow(1, $propostaRow, $clientName);

        // fill all pv columns with 0 first
        foreach ($colMap as $colIdx) {
            $proposta->setCellValueByColumnAndRow($colIdx, $propostaRow, 0);
        }

        $rowTotal = 0;

        foreach ($clientData as $item) {
            if (isset($colMap[$item->pv_id])) {
                $proposta->setCellValueByColumnAndRow($colMap[$item->pv_id], $propostaRow, $item->total);
                $rowTotal += $item->total;
            }
        }

        $proposta->setCellValueByColumnAndRow($totalColIndex, $propostaRow, $rowTotal);
        $propostaRow++;
    }

    // Add total row for Sheet 2
    $proposta->setCellValueByColumnAndRow(1, $propostaRow, 'TOTALE');
    for ($i = 2; $i < $totalColIndex; $i++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $proposta->setCellValue($colLetter . $propostaRow, '=SUM(' . $colLetter . '2:' . $colLetter . ($propostaRow - 1) . ')');
    }
    $totalColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColIndex);
    $proposta->setCellValue($totalColLetter . $propostaRow, '=SUM(' . $totalColLetter . '2:' . $totalColLetter . ($propostaRow - 1) . ')');
    $proposta->getStyle('A' . $propostaRow . ':' . $lastHeaderCol . $propostaRow)->getFont()->setBold(true);

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

    // Get installments grouped by client and category FIRST
    // pv.description2 stores the category id (parameter_order=12) as string
    // IMPORTANT: We should include ALL installments (with or without category) to match Sheet 1 & 2
    $macroData = DB::table('client_pay_installments as cpi')
        ->whereNull('cpi.deleted_at')
        ->join('parameter_values as pv', 'pv.id', '=', 'cpi.parameter_value_id')
        ->whereIn('pv.parameter_id', [8, 9])
        ->leftJoin('parameter_values as cat', function($join) {
            $join->on(DB::raw('CAST(pv.description2 AS UNSIGNED)'), '=', 'cat.id')
                 ->where('cat.parameter_order', '=', 12);
        })
        ->leftJoinSub(
            DB::table('client_pay_installment_sub_data')
                ->whereNull('deleted_at')
                ->select('client_pay_installment_id', DB::raw('SUM(COALESCE(price, 0)) as sub_total'))
                ->groupBy('client_pay_installment_id'),
            'sub_totals',
            'sub_totals.client_pay_installment_id', '=', 'cpi.id'
        )
        ->select(
            'cpi.client_id',
            DB::raw('COALESCE(cat.parameter_value, "Senza Categoria") as category'),
            DB::raw('SUM(COALESCE(cpi.amount, 0) + COALESCE(sub_totals.sub_total, 0)) as total')
        )
        ->groupBy('cpi.client_id', 'cat.parameter_value')
        ->get()
        ->groupBy('client_id');

    // Categories = all those that appear in macroData (including "Senza Categoria" for items without category)
    $categories = collect();
    foreach ($macroData as $clientRows) {
        foreach ($clientRows as $item) {
            if (!$categories->contains('parameter_value', $item->category)) {
                $categories->push((object)['parameter_value' => $item->category]);
            }
        }
    }
    $categories = $categories->sortBy('parameter_value')->values();

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

    $macroRow = 2;

    // Create a map of client_id => ragione_sociale for quick lookup
    $clientNames = [];
    foreach ($clients as $client) {
        $clientNames[$client->id] = $client->ragione_sociale;
    }

    // Iterate over macroData directly to ensure we don't miss any clients
    foreach ($macroData as $clientId => $clientCats) {
        $clientName = $clientNames[$clientId] ?? 'Unknown Client';

        $macro->setCellValueByColumnAndRow(1, $macroRow, $clientName);

        // fill all category columns with 0 first
        foreach ($catColMap as $colIdx) {
            $macro->setCellValueByColumnAndRow($colIdx, $macroRow, 0);
        }

        $rowTotal = 0;

        foreach ($clientCats as $item) {
            if (isset($catColMap[$item->category])) {
                $macro->setCellValueByColumnAndRow($catColMap[$item->category], $macroRow, $item->total);
                $rowTotal += $item->total;  // only sum what's in a known category column
            }
        }

        $macro->setCellValueByColumnAndRow($macroTotalCol, $macroRow, $rowTotal);
        $macroRow++;
    }

    // Add total row for Sheet 3
    $macro->setCellValueByColumnAndRow(1, $macroRow, 'TOTALE');
    for ($i = 2; $i < $macroTotalCol; $i++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $macro->setCellValue($colLetter . $macroRow, '=SUM(' . $colLetter . '2:' . $colLetter . ($macroRow - 1) . ')');
    }
    $macroTotalColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($macroTotalCol);
    $macro->setCellValue($macroTotalColLetter . $macroRow, '=SUM(' . $macroTotalColLetter . '2:' . $macroTotalColLetter . ($macroRow - 1) . ')');
    $macro->getStyle('A' . $macroRow . ':' . $macroLastCol . $macroRow)->getFont()->setBold(true);

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

    // Sum all installments grouped by category — derived directly from macroData (Sheet 3)
    $riepilogoData = collect();
    $catTotals = [];
    foreach ($macroData as $clientRows) {
        foreach ($clientRows as $item) {
            $catTotals[$item->category] = ($catTotals[$item->category] ?? 0) + $item->total;
        }
    }
    ksort($catTotals);
    foreach ($catTotals as $category => $total) {
        $riepilogoData->push((object)['category' => $category, 'total' => $total]);
    }

    $rRow = 2;
    foreach ($riepilogoData as $item) {
        $riepilogo->setCellValue('A' . $rRow, $item->category);
        $riepilogo->setCellValue('B' . $rRow, $item->total);
        $rRow++;
    }

    // Add total row for Sheet 4
    $riepilogo->setCellValue('A' . $rRow, 'TOTALE');
    $riepilogo->setCellValue('B' . $rRow, '=SUM(B2:B' . ($rRow - 1) . ')');
    $riepilogo->getStyle('A' . $rRow . ':B' . $rRow)->getFont()->setBold(true);

    $riepilogo->getStyle('A1:B' . $rRow)
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
