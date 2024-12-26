<?php

namespace App\Models\Parameter;

use App\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;


class ParameterValue extends Model
{
    use HasFactory;
    use SoftDeletes;
    use CreatedUpdatedBy;
    protected $fillable = [
        'parameter_id',
        'parameter_value',
        'description',
        'parameter_order'
    ];


    public function scopeParameterOrder($query, $paraOrder)
    {
        return $query->where('parameter_id', $paraOrder);

    }

}
