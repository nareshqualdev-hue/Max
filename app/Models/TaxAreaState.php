<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxAreaState extends Model
{
    protected $table = 'pu_tax_areas_states';

    protected $primaryKey = 'taxt_areas_state_id';

    public $timestamps = false;

    protected $fillable = [
        'state',
        'shipping',
        'insurance',
        'signature',
        'tax_rate',
        'created_date',
    ];

    protected $casts = [
        'tax_rate' => 'float',
    ];
}
?>