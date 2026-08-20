<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $site_offers_id
 * @property string     $offer_title
 * @property string     $description
 * @property string     $offer_link_text
 * @property string     $offer_link
 * @property Date       $expiry_date
 */
class SiteOffers extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_site_offers';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'site_offers_id', 'offer_title', 'description', 'offer_link_text', 'offer_link', 'expiry_date', 'section_id', 'status'
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
        'site_offers_id' => 'int', 'offer_title' => 'string', 'description' => 'string', 'offer_link_text' => 'string', 'offer_link' => 'string', 'expiry_date' => 'date'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'expiry_date'
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
