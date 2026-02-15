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
    $headers = ['Cliente', 'Descrizione', 'Totale'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    $sheet->getStyle('A1:C1')->getFont()->setBold(true);
    $sheet->getStyle('A1:C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $row = 2;

    foreach ($clients as $client) {

        // Get installments per client
        $installments = DB::table('client_pay_installments as cpi')
            ->where('cpi.client_id', $client->id)
            ->whereNull('cpi.deleted_at')
            ->leftJoin('parameter_values as pv', 'pv.id', '=', 'cpi.parameter_value_id')
            ->select(
                'cpi.id',
                'pv.parameter_value as description',
                'cpi.amount'
            )
            ->get();

        foreach ($installments as $installment) {

            // Main installment row
            $sheet->setCellValue('A' . $row, $client->ragione_sociale);
            $sheet->setCellValue('B' . $row, $installment->description);
            $sheet->setCellValue('C' . $row, $installment->amount);
            $row++;

            // Sub installments (each as separated row)
            $subs = DB::table('client_pay_installment_sub_data as sub')
                ->where('sub.client_pay_installment_id', $installment->id)
                ->whereNull('sub.deleted_at')
                ->leftJoin('parameter_values as pv', 'pv.id', '=', 'sub.parameter_value_id')
                ->select(
                    'pv.parameter_value as description',
                    'sub.price'
                )
                ->get();

            foreach ($subs as $sub) {
                $sheet->setCellValue('A' . $row, $client->ragione_sociale);
                $sheet->setCellValue('B' . $row, $sub->description);
                $sheet->setCellValue('C' . $row, $sub->price);
                $row++;
            }
        }
    }

    // Borders & styling
    $sheet->getStyle('A1:C' . ($row - 1))
        ->getBorders()
        ->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);

    foreach (range('A', 'C') as $colLetter) {
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }

    $sheet->setAutoFilter('A1:C1');

    // Save Excel
    $fileName = 'client_installments_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
    $filePath = 'client_installments_exports/' . $fileName;

    ob_start();
    (new Xlsx($spreadsheet))->save('php://output');
    $excelOutput = ob_get_clean();

    Storage::disk('public')->put($filePath, $excelOutput);

    return response()->json([
        'path' => asset('storage/' . $filePath),
    ]);
}

}
