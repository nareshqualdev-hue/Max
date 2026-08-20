<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int        $email_templates_id
 * @property string     $title
 * @property string     $subject
 * @property string     $mail_body
 * @property string     $template_var_name
 */
class EmailTemplates extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_email_templates';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'email_templates_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'subject', 'mail_body', 'template_var_name', 'status'
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
        'email_templates_id' => 'int', 'title' => 'string', 'subject' => 'string', 'mail_body' => 'string', 'template_var_name' => 'string'
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
