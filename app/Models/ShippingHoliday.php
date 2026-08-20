<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $holiday_id
 * @property Date       $holiday_date
 * @property string     $holiday_name
 */
class ShippingHoliday extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_shipping_holiday';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'holiday_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'holiday_date', 'holiday_name', 'holiday_status'
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
        'holiday_id' => 'int', 'holiday_date' => 'date', 'holiday_name' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'holiday_date'
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
