<?php
namespace App\Services\Reports;
use App\Enums\Task\TaskStatus;
use App\Models\Client;
use App\Models\Task\Task;
use App\Models\Invoice\Invoice;
use Illuminate\Support\Facades\DB;



class ReportService
{
     public function reports()
    {
        $clients=DB::table('clients')->count();
        $invoiced =Task::whereNotNull('invoice_id')->count();
        $notInvoiced =Task::where('invoice_id',null)->count();
        $toWork =Task::where('status',TaskStatus::TO_WORK->value)->count();
        $inProgress =Task::where('status',TaskStatus::IN_PROGRESS->value)->count();
        $done =Task::where('status',TaskStatus::DONE->value)->count();
        return response()->json([
            "clients"=>$clients,
            "invoices"=>[
                "invoiced"=>$invoiced,
                "notInvoiced"=>$notInvoiced
            ],
            "tasks"=>[
                "toWork"=>$toWork,
                "inProgress"=>$inProgress,
                "done"=>$done,
            ]
          ],200);


    }
}

