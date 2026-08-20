<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $pm_id
 * @property int        $pm_position
 * @property string     $pm_name
 * @property string     $pm_group_name
 * @property string     $passwordpm_gateway_name
 * @property string     $pm_details
 * @property string     $pm_short_desc
 * @property string     $pm_type
 */
class PaymentMethod extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_payment_methods';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'pm_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'pm_name', 'pm_group_name', 'pm_gateway_name', 'pm_details', 'pm_short_desc', 'pm_type', 'pm_status', 'pm_position'
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
        'pm_name' => 'string', 'pm_group_name' => 'string', 'pm_gateway_name' => 'string', 'pm_details' => 'string', 'pm_short_desc' => 'string', 'pm_type' => 'string', 'pm_position' => 'int'
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
