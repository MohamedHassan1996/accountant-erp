<?php

namespace App\Http\Controllers\Api\Private\Client;

use App\Http\Controllers\Controller;
use App\Services\Task\ExportTaskService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
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

        // 1. جلب البيانات الأساسية والفرعية في مجموعة واحدة موحدة لضمان تطابق الأرقام
        $rawInstallments = DB::table('client_pay_installments as cpi')
            ->whereNull('cpi.deleted_at')
            ->join('clients as c', 'c.id', '=', 'cpi.client_id')
            ->whereNull('c.deleted_at')
            ->leftJoin('parameter_values as pv', 'pv.id', '=', 'cpi.parameter_value_id')
            ->whereIn('pv.parameter_id', [8, 9])
            ->select(
                'cpi.id',
                'cpi.client_id',
                'cpi.start_at',
                'c.ragione_sociale',
                'pv.id as pv_id',
                'pv.parameter_value as pv_name',
                'pv.description as description',
                'pv.description2 as category_id', // معرف الفئة
                'cpi.amount'
            )
            ->get();

        $allTransactions = collect();

        foreach ($rawInstallments as $inst) {
            // إضافة المبلغ الأساسي
            $allTransactions->push([
                'client_id'       => $inst->client_id,
                'ragione_sociale' => $inst->ragione_sociale,
                'date'            => $inst->start_at ? Carbon::parse($inst->start_at)->format('d/m/Y') : '',
                'description'     => $inst->description,
                'pv_id'           => $inst->pv_id,
                'pv_name'         => $inst->pv_name,
                'category_id'     => $inst->category_id,
                'amount'          => (float)($inst->amount ?? 0)
            ]);

            // إضافة المبالغ الفرعية (Sub Installments)
            $subs = DB::table('client_pay_installment_sub_data as sub')
                ->where('sub.client_pay_installment_id', $inst->id)
                ->whereNull('sub.deleted_at')
                ->leftJoin('parameter_values as pv_sub', 'pv_sub.id', '=', 'sub.parameter_value_id')
                ->select('pv_sub.description', 'sub.price', 'pv_sub.id as pv_id', 'pv_sub.parameter_value as pv_name', 'pv_sub.description2 as category_id')
                ->get();

            foreach ($subs as $sub) {
                $allTransactions->push([
                    'client_id'       => $inst->client_id,
                    'ragione_sociale' => $inst->ragione_sociale,
                    'date'            => $inst->start_at ? Carbon::parse($inst->start_at)->format('d/m/Y') : '',
                    'description'     => $sub->description,
                    'pv_id'           => $sub->pv_id ?? $inst->pv_id, // إذا لم يوجد نوع للفرعي نأخذ نوع الرئيسي
                    'pv_name'         => $sub->pv_name ?? $inst->pv_name,
                    'category_id'     => $sub->category_id ?? $inst->category_id,
                    'amount'          => (float)($sub->price ?? 0)
                ]);
            }
        }

        // جلب أسماء الفئات (Macro Servizi) للقاموس
        $categoryNames = DB::table('parameter_values')
            ->where('parameter_order', 12)
            ->pluck('parameter_value', 'id')
            ->toArray();

        // ===================== الصفحة 1: Dettaglio =====================
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Dettaglio');
        $headers = ['Cliente', 'Start Date', 'Descrizione', 'Totale'];
        $sheet->fromArray($headers, NULL, 'A1');

        $row = 2;
        foreach ($allTransactions as $trans) {
            $sheet->setCellValue('A' . $row, $trans['ragione_sociale']);
            $sheet->setCellValue('B' . $row, $trans['date']);
            $sheet->setCellValue('C' . $row, $trans['description']);
            $sheet->setCellValue('D' . $row, $trans['amount']);
            $row++;
        }
        $sheet->setCellValue('A' . $row, 'TOTALE');
        $sheet->setCellValue('D' . $row, "=SUM(D2:D" . ($row - 1) . ")");
        $this->applyStyle($sheet, 'D', $row);

        // ===================== الصفحة 2: Proposta =====================
        $proposta = $spreadsheet->createSheet();
        $proposta->setTitle('Proposta');

        $pvGroups = $allTransactions->groupBy('pv_id');
        $clientGroups = $allTransactions->groupBy('client_id');

        $col = 2;
        $pvColMap = [];
        $proposta->setCellValueByColumnAndRow(1, 1, 'Cliente');

        foreach ($pvGroups as $pvId => $items) {
            $pvName = $items->first()['pv_name'] ?? 'N/A';
            $proposta->setCellValueByColumnAndRow($col, 1, $pvName);
            $pvColMap[$pvId] = $col;
            $col++;
        }
        $totalColIdx = $col;
        $proposta->setCellValueByColumnAndRow($totalColIdx, 1, 'Totale');

        $pRow = 2;
        foreach ($clientGroups as $clientId => $transactions) {
            $proposta->setCellValueByColumnAndRow(1, $pRow, $transactions->first()['ragione_sociale']);
            foreach ($pvColMap as $pvId => $colIdx) {
                $sum = $transactions->where('pv_id', $pvId)->sum('amount');
                $proposta->setCellValueByColumnAndRow($colIdx, $pRow, $sum);
            }
            $proposta->setCellValueByColumnAndRow($totalColIdx, $pRow, $transactions->sum('amount'));
            $pRow++;
        }
        $this->addFooterTotals($proposta, $pRow, $totalColIdx);

        // ===================== الصفحة 3: Macro_Servizi =====================
        $macro = $spreadsheet->createSheet();
        $macro->setTitle('Macro_Servizi');

        $allTransactions = $allTransactions->map(function($item) use ($categoryNames) {
            $item['cat_name'] = $categoryNames[$item['category_id']] ?? 'Senza Categoria';
            return $item;
        });

        $catGroups = $allTransactions->groupBy('cat_name');
        $catColMap = [];
        $col = 2;
        $macro->setCellValueByColumnAndRow(1, 1, 'Cliente');
        foreach ($catGroups->keys()->sort() as $catName) {
            $macro->setCellValueByColumnAndRow($col, 1, $catName);
            $catColMap[$catName] = $col;
            $col++;
        }
        $mTotalColIdx = $col;
        $macro->setCellValueByColumnAndRow($mTotalColIdx, 1, 'Totale');

        $mRow = 2;
        foreach ($clientGroups as $clientId => $transactions) {
            $macro->setCellValueByColumnAndRow(1, $mRow, $transactions->first()['ragione_sociale']);
            foreach ($catColMap as $catName => $colIdx) {
                $sum = $transactions->where('cat_name', $catName)->sum('amount');
                $macro->setCellValueByColumnAndRow($colIdx, $mRow, $sum);
            }
            $macro->setCellValueByColumnAndRow($mTotalColIdx, $mRow, $transactions->sum('amount'));
            $mRow++;
        }
        $this->addFooterTotals($macro, $mRow, $mTotalColIdx);

        // ===================== الصفحة 4: Riepilogo =====================
        $riepilogo = $spreadsheet->createSheet();
        $riepilogo->setTitle('Riepilogo');
        $riepilogo->setCellValue('A1', 'Macro Servizi');
        $riepilogo->setCellValue('B1', 'Totale');

        $rRow = 2;
        foreach ($allTransactions->groupBy('cat_name') as $catName => $items) {
            $riepilogo->setCellValue('A' . $rRow, $catName);
            $riepilogo->setCellValue('B' . $rRow, $items->sum('amount'));
            $rRow++;
        }
        $riepilogo->setCellValue('A' . $rRow, 'TOTALE');
        $riepilogo->setCellValue('B' . $rRow, "=SUM(B2:B" . ($rRow - 1) . ")");
        $this->applyStyle($riepilogo, 'B', $rRow);

        // التخزين والتحميل
        $fileName = 'client_installments_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
        $filePath = 'client_installments_exports/' . $fileName;

        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        $excelOutput = ob_get_clean();
        Storage::disk('public')->put($filePath, $excelOutput);

        return response()->json(['path' => Storage::disk('public')->url($filePath)]);
    }

    private function applyStyle($sheet, $lastColLetter, $lastRow)
    {
        $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColLetter}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (range('A', $lastColLetter) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function addFooterTotals($sheet, $row, $lastColIdx)
    {
        $sheet->setCellValueByColumnAndRow(1, $row, 'TOTALE');
        for ($i = 2; $i <= $lastColIdx; $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->setCellValue($colLetter . $row, "=SUM({$colLetter}2:{$colLetter}" . ($row - 1) . ")");
        }
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIdx);
        $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColLetter}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        for ($i = 1; $i <= $lastColIdx; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
    }
}
