<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPagesData extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_landing_pages_data';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'data_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'section_id	', 'banner', 'banner_link', 'banner_title', 'banner_desc', 'banner_link_title', 'rank', 'status'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        
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

    public function landingPage(){
		return $this->hasOne(LandingPages::class,'section_id','section_id');
	}

}
