<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteControl extends Model
{
    protected $table = 'pu_site_controls';

    protected $primaryKey = 'site_control_id';

    public $timestamps = false; // No created_at / updated_at

    protected $fillable = [
        'title',
        'var_name',
        'description',
        'site_control',
        'section',
        'status'
    ];

    protected $casts = [
        'section' => 'integer',
        'status'  => 'string', // enum('0','1')
    ];

    /*
    |--------------------------------------------------------------------------
    | Optional: Accessor for readable status
    |--------------------------------------------------------------------------
    */

    public function getStatusLabelAttribute()
    {
        return $this->status === '1' ? 'Active' : 'Inactive';
    }

    /*
    |--------------------------------------------------------------------------
    | Optional: Scope for Active Records
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', '1');
    }
}
