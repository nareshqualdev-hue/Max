<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $mail_banner_id
 * @property string     $mail_banner_title
 * @property string     $mail_banner_image
 * @property string     $mail_banner_link
 */
class MailBanner extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_mail_banner';

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
        'mail_banner_id', 'mail_banner_title', 'mail_banner_image', 'mail_banner_link', 'status'
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
        'mail_banner_id' => 'int', 'mail_banner_title' => 'string', 'mail_banner_image' => 'string', 'mail_banner_link' => 'string'
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
