<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $address_book_id
 * @property int        $customer_id
 * @property string     $title
 * @property string     $first_name
 * @property string     $last_name
 * @property string     $company_name
 * @property string     $address1
 * @property string     $address2
 * @property string     $phone
 * @property string     $fax
 * @property string     $city
 * @property string     $state
 * @property string     $zip
 * @property string     $country
 * @property string     $email
 */
class AdminCreditLog extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_admin_credit_logs';

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
        'customer_id', 'admin_id', 'old_amount', 'credited_amount', 'new_amount', 'date', 'note'
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
