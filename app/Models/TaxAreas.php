<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $tax_areas_id
 * @property string     $country
 * @property string     $states
 * @property string     $zip_from
 * @property string     $zip_to
 * @property string     $details
 */
class TaxAreas extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_tax_areas';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'tax_areas_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country', 'states', 'zip_from', 'zip_to', 'details', 'status','ShippingTaxable'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'tax_areas_id' => 'int', 'country' => 'string', 'states' => 'string', 'zip_from' => 'string', 'zip_to' => 'string', 'details' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    // Scopes...

    // Functions ...

    // Relations ...
}
