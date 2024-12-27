<?php

namespace App\Models\Task;

use App\Enums\Task\TaskTimeLogStatus;
use App\Enums\Task\TaskTimeLogType;
use App\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskTimeLog extends Model
{
    use HasFactory, SoftDeletes, CreatedUpdatedBy;

    protected $fillable = [
        'start_at',
        'end_at',
        'type',
        'comment',
        'task_id',
        'time_log_id',
        'user_id',
    ];

    protected $casts = [
        'status' => TaskTimeLogType::class,
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

}
