<?php

namespace App\Models\Task;

use App\Enums\Task\TaskStatus;
use App\Enums\Task\TaskTimeLogType;
use App\Models\Client\Client;
use App\Models\ServiceCategory\ServiceCategory;
use App\Models\User;
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
        'description',
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

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function serviceCategory()
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    public function timeLogs()
    {
        return $this->hasMany(TaskTimeLog::class);
    }
    public function getTotalHoursAttribute()
    {
        $backTimeLogs = $this->timeLogs()
        ->where('type', \App\Enums\Task\TaskTimeLogType::BACK_TIME_LOG->value)
        ->whereNotNull('end_at')
        ->get();

    if ($backTimeLogs->isNotEmpty()) {
        $totalMinutes = $backTimeLogs->sum(function ($log) {
            return $log->start_at && $log->end_at
                ? $log->end_at->diffInMinutes($log->start_at)
                : 0;
        });

        return number_format($totalMinutes / 60, 2);
    }

    // If no BACK_TIME_LOG, calculate TIME_LOG
    $timeLogs = $this->timeLogs()
        ->where('type', TaskTimeLogType::TIME_LOG->value)
        ->whereNotNull('end_at')
        ->get();

    $totalMinutes = $timeLogs->sum(function ($log) {
        return $log->start_at && $log->end_at
            ? $log->end_at->diffInMinutes($log->start_at)
            : 0;
    });

    return $totalMinutes == 0 ? 0 : number_format($totalMinutes / 60, 2);
    }

    public function getTotalPriceAfterDiscountAttribute()
    {
        return $this->client->getClientDiscount($this->service_category_id);
    }

}
