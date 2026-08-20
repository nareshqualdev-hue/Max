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
class AddressBook extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_address_book';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'address_book_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id', 'title', 'first_name', 'last_name', 'company_name', 'address1', 'address2', 'phone', 'fax', 'city', 'state', 'zip', 'country', 'email', 'status'
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
        'address_book_id' => 'int', 'customer_id' => 'int', 'title' => 'string', 'first_name' => 'string', 'last_name' => 'string', 'company_name' => 'string', 'address1' => 'string', 'address2' => 'string', 'phone' => 'string', 'fax' => 'string', 'city' => 'string', 'state' => 'string', 'zip' => 'string', 'country' => 'string', 'email' => 'string'
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
