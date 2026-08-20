<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $log_id
 * @property int        $customer_id
 * @property int        $admin_id
 * @property string     $note
 */
class AdminFundLog extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_admin_fund_log';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'log_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id', 'admin_id', 'old_fund_value', 'funded_amount', 'new_fund_value', 'date', 'note'
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
        'log_id' => 'int', 'customer_id' => 'int', 'admin_id' => 'int', 'date' => 'timestamp', 'note' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'date'
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
