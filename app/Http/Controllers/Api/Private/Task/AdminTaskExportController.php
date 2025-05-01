<?php
namespace App\Http\Controllers\Api\Private\Task;

use App\Enums\Task\TaskStatus;
use App\Exports\TasksExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminTask\AllAdminTaskResource;
use App\Services\Task\ExportTaskService;
use App\Services\Upload\UploadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;


class AdminTaskExportController extends Controller
{
    protected $taskService;


    public function __construct(ExportTaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function index(Request $request)
    {
    // Get task data from service
        $tasks = $this->taskService->allTasks();

        // Transform using your resource
        $transformed = AllAdminTaskResource::collection($tasks['tasks'])->toArray($request);

        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Define headers
        $headers = [
            'Numero ticket','Cliente' ,'Oggetto', 'Servizio', 'Utente', 'Totale ore', 'Ora inizio','Data creazione', 'Stato'
        ];

        // Fill header row
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Style header
        $sheet->getStyle('A1:' . chr(ord('A') + count($headers) - 1) . '1')
            ->getFont()->setBold(true);
        $sheet->getStyle('A1:' . chr(ord('A') + count($headers) - 1) . '1')
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:' . chr(ord('A') + count($headers) - 1) . '1')
            ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // Fill data rows
        $row = 2;
        $statusTranslation = [
            '0' => 'aperto',
            '1' => 'in lavorazione',
            '2' => 'chiuso',
        ];

        foreach ($transformed as $item) {

            $item['status'] = $statusTranslation[$item['status']->value];


// Parse using the correct format (day/month/year hour:minute:second)
        $carbonDate = Carbon::createFromFormat('d/m/Y H:i:s', $item['startTime']);

// Format it with AM/PM
        $formatted = $carbonDate->format('d/m/Y h:i:s A');

            $sheet
            ->setCellValue('A' . $row, $item['number'] ?? '')
            ->setCellValue('B' . $row, $item['clientName'] ?? '')
                ->setCellValue('C' . $row, $item['title'] ?? '')
                ->setCellValue('D' . $row, $item['serviceCategoryName'] ?? '')
                ->setCellValue('E' . $row, $item['accountantName'] ?? '')
                ->setCellValue('F' . $row, $item['totalHours'] ?? '')
                ->setCellValue('G' . $row, $formatted ?? '')
                ->setCellValue('H' . $row, $item['createdAt'] ?? '')
                ->setCellValue('I' . $row, $item['status'] ?? '');
                // ->setCellValue('N' . $row, $item['startTime'] ?? '');
            $row++;
        }



        // Apply border
        $sheet->getStyle('A1:I' . ($row - 1))
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Auto size columns
        foreach (range('A', 'I') as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Auto filter
        $sheet->setAutoFilter('A1:I1');

        // Save file
        $fileName = 'tasks_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
        $filePath = 'public/tasks_exports/' . $fileName;

        $writer = new Xlsx($spreadsheet);
        Storage::makeDirectory('public/tasks_exports'); // Make sure directory exists
        $writer->save(storage_path('app/' . $filePath));

        // Generate public URL
        $url = asset('storage/tasks_exports/' . $fileName);

        return response()->json([
            'path' => 'https://accountant-api.testingelmo.com/'. $url,
        ]);
    }


}
