<?php
namespace App\Services\Reports;

use App\Enums\Task\TaskStatus;
use App\Models\Client\Client;
use App\Models\Task\Task;

class ReportService
{
    public function reports()
    {
        $authUser = auth()->user();

        $clients = Client::whereNull('deleted_at')->count();
        $invoiced = Task::whereNull('deleted_at')->whereNotNull('invoice_id')->count();
        $notInvoiced = Task::whereNull('deleted_at')->whereNull('invoice_id')->count();
        $toWork = Task::whereNull('deleted_at')->where('status', TaskStatus::TO_WORK->value)->where('user_id', $authUser->id)->count();
        $inProgress = Task::whereNull('deleted_at')->where('status', TaskStatus::IN_PROGRESS->value)->where('user_id', $authUser->id)->count();
        $done = Task::whereNull('deleted_at')->where('status', TaskStatus::DONE->value)->where('user_id', $authUser->id)->count();

        return response()->json([
            "clients" => $clients,
            "invoices" => [
                "invoiced" => $invoiced,
                "notInvoiced" => $notInvoiced
            ],
            "tasks" => [
                "toWork" => $toWork,
                "inProgress" => $inProgress,
                "done" => $done,
            ]
        ], 200);
    }
}
