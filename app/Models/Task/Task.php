<?php

namespace App\Models\Task;

use App\Enums\Task\TaskStatus;
use App\Enums\Task\TaskTimeLogStatus;
use App\Enums\Task\TaskTimeLogType;
use App\Models\Client\Client;
use App\Models\ServiceCategory\ServiceCategory;
use App\Models\User;
use App\Traits\CreatedUpdatedBy;
use Carbon\Carbon;
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
        'connection_type_id'
    ];

    protected $casts = [
        'status' => TaskStatus::class
    ];

    public static function boot()
    {
        parent::boot();
        static::created(function ($model) {
            $model->number = 'T_' . str_pad($model->id, 5, '0', STR_PAD_LEFT);
            $model->save();
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
        ->where('type', TaskTimeLogType::BACK_TIME_LOG->value)
        ->whereNotNull('end_at')
        ->get();

    if ($backTimeLogs->isNotEmpty()) {
        $totalMinutes = $backTimeLogs->sum(function ($log) {
            return $log->total_time;
        });

        return number_format($totalMinutes / 60, 2);
    }

    // If no BACK_TIME_LOG, calculate TIME_LOG
    $timeLogs = $this->timeLogs()
        ->where('type', TaskTimeLogType::TIME_LOG->value)
        ->get();

    $totalMinutes = $timeLogs->sum(function ($log) {
        return $log->end_at
            ? $log->total_time
            : Carbon::now()->diffInMinutes($log->start_at);
    });

    return $totalMinutes == 0 ? 0 : number_format($totalMinutes / 60, 2);
    }

    public function getCurrentTimeAttribute()
    {
        $timeLogs = $this->timeLogs()
            ->where('type', TaskTimeLogType::TIME_LOG->value)
            ->get();

        $totalSeconds = $timeLogs->sum(function ($log) {
            return $log->end_at
                ? $log->total_time * 60// Convert total_mins to total_seconds
                : Carbon::now()->diffInSeconds($log->start_at); // Use diffInSeconds for accuracy
        });

        // Handle large hours manually if exceeding Carbon's default formatting
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }


    public function getTotalPriceAfterDiscountAttribute()
    {
        return $this->client->getClientDiscount($this->service_category_id);
    }

    public function getTimeLogStatusAttribute()
    {
        return $this->timeLogs()->latest()->first()->status->value ?? TaskTimeLogStatus::from(3)->value;
    }

    public function getLatestTimeLogIdAttribute()
    {
        return $this->timeLogs()->where('status', TaskTimeLogStatus::START->value)->latest()->first()->id ?? "";
    }

}
