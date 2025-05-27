<?php
namespace App\Http\Controllers\Api\Private\Task;

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


class AdminTaskExportController extends Controller
{
    protected $taskService;


    public function __construct(ExportTaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function index(Request $request)
    {
        // Fetch and transform tasks
        $tasks = $this->taskService->allTasks();
        $transformed = AllAdminTaskResource::collection($tasks['tasks'])->toArray($request);

        // Initialize spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = [
            'Numero ticket', 'Cliente', 'Oggetto', 'Servizio',
            'Utente', 'Totale ore', 'Ora inizio', 'Data creazione', 'Stato'
        ];

        // Populate header row
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Style headers
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:I1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // Fill data
        $row = 2;
        $statusTranslation = [
            '0' => 'aperto',
            '1' => 'in lavorazione',
            '2' => 'chiuso',
        ];

        foreach ($transformed as $item) {
            $item['status'] = $statusTranslation[$item['status']->value] ?? $item['status'];

            $formattedStart = '';
            if (!empty($item['startTime'])) {
                try {
                    $carbonDate = Carbon::createFromFormat('d/m/Y H:i:s', $item['startTime']);
                    $formattedStart = $carbonDate->format('d/m/Y h:i:s A');
                } catch (\Exception $e) {
                    $formattedStart = $item['startTime'];
                }
            }

            $sheet
                ->setCellValue("A$row", $item['number'] ?? '')
                ->setCellValue("B$row", $item['clientName'] ?? '')
                ->setCellValue("C$row", $item['title'] ?? '')
                ->setCellValue("D$row", $item['serviceCategoryName'] ?? '')
                ->setCellValue("E$row", $item['accountantName'] ?? '')
                ->setCellValue("F$row", $item['totalHours'] ?? '')
                ->setCellValue("G$row", $formattedStart)
                ->setCellValue("H$row", $item['createdAt'] ?? '')
                ->setCellValue("I$row", $item['status'] ?? '');
            $row++;
        }

        // Add SUM formula to column F (Total Hours)
        $sheet->setCellValue("E$row", 'Totale');
        $sheet->setCellValue("F$row", '=SUM(F2:F' . ($row - 1) . ')');
        $sheet->getStyle("E$row:F$row")->getFont()->setBold(true);
        $sheet->getStyle("E$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Apply borders and column width
        $sheet->getStyle("A1:I$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (range('A', 'I') as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
        $sheet->setAutoFilter('A1:I1');

        // Save to memory and disk
        $fileName = 'tasks_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
        $filePath = 'tasks_exports/' . $fileName;

        ob_start();
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        $excelOutput = ob_get_clean();

        Storage::disk('public')->put($filePath, $excelOutput);

        $url = asset('storage/' . $filePath);

        return response()->json([
            'path' => 'https://accountant-api.testingelmo.com' . parse_url($url, PHP_URL_PATH),
        ]);
    }
}
