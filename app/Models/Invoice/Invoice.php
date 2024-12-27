<?php

namespace App\Models\Invoice;

use App\Models\Client\Client;
use App\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, CreatedUpdatedBy;

    protected $fillable = [
        'client_id',
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->number = 'IN' . generateUniqNumber();
        });
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function invoiceDetails(): HasMany
    {
        return $this->hasMany(InvoiceDetail::class, 'invoice_id');
    }
}
