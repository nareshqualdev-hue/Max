<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $did
 * @property string     $deal_title
 * @property int        $deal_rank
 */
class Dealofweektitle extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_dealofweektitle';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'did';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'deal_title', 'deal_rank'
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
        'did' => 'int', 'deal_title' => 'string', 'deal_rank' => 'int'
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
