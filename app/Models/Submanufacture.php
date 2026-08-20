<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $imanufactureid
 * @property string     $title
 * @property string     $left_description
 * @property string     $right_desciption
 * @property string     $button_link
 * @property string     $image
 */
class Submanufacture extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_submanufacture';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'imanufactureid';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'left_description', 'right_desciption', 'button_link', 'image', 'status'
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
        'imanufactureid' => 'int', 'title' => 'string', 'left_description' => 'string', 'right_desciption' => 'string', 'button_link' => 'string', 'image' => 'string'
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
