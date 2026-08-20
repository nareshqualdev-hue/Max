<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $attrid
 * @property string     $attributevalue
 */
class CustomerAttribute extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_customer_attribute';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'attrid';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'attributename', 'attributevalue'
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
        'attrid' => 'int', 'attributevalue' => 'string'
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
