<?php

namespace App\Http\Resources\AdminTask;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class AllAdminTaskCollection extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */

     private $pagination;

     public function __construct($resource)
     {
         $this->pagination = [
             'total' => $resource->total(),
             'count' => $resource->count(),
             'per_page' => $resource->perPage(),
             'current_page' => $resource->currentPage(),
             'total_pages' => $resource->lastPage()
         ];

         $resource = $resource->getCollection();

         parent::__construct($resource);
     }


    public function toArray(Request $request): array
    {
        $hours=floor(DB::table('task_time_logs')->sum('total_time')/60);
        $minutes=DB::table('task_time_logs')->sum('total_time')%60;
        $taskTimeLogs=sprintf('%d:%02d', $hours, $minutes);

        return [
            "result" => [
                'tasks' => AllAdminTaskResource::collection(($this->collection)->values()->all()),
                "totalHours"=>$taskTimeLogs
            ],
            'pagination' => $this->pagination
        ];

    }
}
