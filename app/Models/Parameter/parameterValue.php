<?php

namespace App\Models\Parameter;

use App\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;


class ParameterValue extends Model
{
    use HasFactory;
    use SoftDeletes;
    use CreatedUpdatedBy;

    protected $primaryKey = 'guid';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'data_creazione';
    const UPDATED_AT = 'versione';
    protected $fillable = [
        'parameter_id',
        'parameter_value',
        'description',
        'parameter_order'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->guid = Str::uuid();
        });
    }

    public function scopeParameterOrder($query, $paraOrder)
    {
        return $query->where('parameter_id', $paraOrder);

    }

}
