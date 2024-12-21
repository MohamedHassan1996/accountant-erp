<?php

namespace App\Models\Task;

use App\Enums\Task\TaskStatus;
use App\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes, CreatedUpdatedBy;

    protected $fillable = [
        'title',
        'status',
        'client_id',
        'user_id',
        'service_category_id',
        'invoice_id',
    ];

    protected $casts = [
        'status' => TaskStatus::class
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->number = 'T' . generateUniqNumber();
        });
    }
}
