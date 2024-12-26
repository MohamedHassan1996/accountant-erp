<?php

namespace App\Models\Task;

use App\Enums\Task\TaskStatus;
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
        $totalMinutes = $this->timeLogs()
            ->whereNotNull('end_at') // Exclude logs where end_at is null
            ->get()
            ->sum(function ($log) {
                return $log->start_at && $log->end_at
                    ? $log->end_at->diffInMinutes($log->start_at)
                    : 0;
            });

        // Convert total minutes to decimal hours
        $totalHours = $totalMinutes / 60;

        // Format to 1 decimal place
        return $totalHours == 0 ? 0 : number_format($totalHours, 2);
    }

}
