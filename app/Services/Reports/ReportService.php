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
        $Invoiced =Task::whereNotNull('invoice_id')->count();
        $NotInvoiced =Task::where('invoice_id',null)->count();
        $Towork =Task::where('status',TaskStatus::TO_WORK)->count();
        $Inprogress =Task::where('status',TaskStatus::IN_PROGRESS)->count();
        $done =Task::where('status',TaskStatus::DONE)->count();
        return response()->json([
            "clients"=>$clients,
            "invoices"=>[
                "invoiced"=>$Invoiced,
                "notInvoiced"=>$NotInvoiced
            ],
            "tasks"=>[
                "toWork"=>$Towork,
                "inProgress"=>$Inprogress,
                "done"=>$done,
            ]
          ],200);


    }
}

