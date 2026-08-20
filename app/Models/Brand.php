<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $brand_id
 * @property int        $imanufactureid
 * @property string     $brand_name
 * @property int        $display_position
 */
class Brand extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_brand';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'brand_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'imanufactureid', 'brand_name', 'status', 'display_position'
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
        'brand_id' => 'int', 'imanufactureid' => 'int', 'brand_name' => 'string', 'display_position' => 'int'
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
	
	public function manufacturer()
	{
		return $this->belongsTo(Manufacture::class,'imanufactureid');
	}
}
