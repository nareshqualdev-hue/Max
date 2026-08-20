<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPagesPreview extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_landing_pages_preview';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'section_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'section_name', 'section_rank', 'page_type', 'status'
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
	
	public function PageData()
	{
		return $this->hasMany(LandingPagesDataPreview::class,'section_id')->orderBy('rank');
	}
}
