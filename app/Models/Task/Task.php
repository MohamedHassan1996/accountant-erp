<?php

namespace App\Models\Task;

use App\Enums\Task\TaskStatus;
use App\Enums\Task\TaskTimeLogStatus;
use App\Enums\Task\TaskTimeLogType;
use App\Models\Client\Client;
use App\Models\Invoice\InvoiceDetail;
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
        'connection_type_id',
        'start_date',
        'end_date',
        'price',
        'price_after_discount'
    ];

    protected $casts = [
        'status' => TaskStatus::class
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function($model){
            $model->is_new = 1;
        });
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

    public function latestTimeLog()
    {
        return $this->hasOne(TaskTimeLog::class)->latestOfMany();
    }
    public function getTotalHoursAttribute()
    {
        $latestTimeLog = $this->timeLogs()
            ->where('type', TaskTimeLogType::TIME_LOG->value)
            ->latest()
            ->first();
            if($latestTimeLog == null){
                return "00:00:00";
            }
        $totalTime = $latestTimeLog->total_time; // Ensure it's an integer

        if ($latestTimeLog->status == TaskTimeLogStatus::START) {
            $totalTime = Carbon::parse($totalTime)->addSeconds(Carbon::now()->diffInSeconds($latestTimeLog->created_at));
        }

        return Carbon::parse($totalTime)->format('H:i:s');
    }
    public function getCurrentTimeAttribute()
    {
        $latestTimeLog = $this->timeLogs()
        ->where('type', TaskTimeLogType::TIME_LOG->value)
        ->latest()
        ->first();


        if(empty($latestTimeLog)){
            return "00:00:00";
        }

        $currentTime = $latestTimeLog->total_time;


        if ($latestTimeLog->status == TaskTimeLogStatus::START) {
            $currentTime = Carbon::parse($currentTime)->addSeconds(Carbon::now()->diffInSeconds($latestTimeLog->created_at));
        }

        return Carbon::parse($currentTime)->format('H:i:s');

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

    public function invoiceDetails()
    {
        return $this->morphMany(InvoiceDetail::class, 'invoiceable');
    }

}
