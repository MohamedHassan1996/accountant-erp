<?php

namespace App\Http\Resources\AdminTask;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AllAdminTaskCollection extends ResourceCollection
{
    private $pagination;
    private $extraData; // Store additional data (e.g., total time)

    public function __construct($resource, $extraData = [])
    {
        $this->pagination = [
            'total' => $resource->total(),
            'count' => $resource->count(),
            'per_page' => $resource->perPage(),
            'current_page' => $resource->currentPage(),
            'total_pages' => $resource->lastPage()
        ];

        $this->extraData = $extraData; // Store extra data like totalHours
        $resource = $resource->getCollection();

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            "result" => [
                'tasks' => AllAdminTaskResource::collection(($this->collection)->values()->all()),
                "totalHours" => $this->extraData['totalHours'] ?? "0:00" // Handle missing totalHours
            ],
            'pagination' => $this->pagination
        ];
    }
}
