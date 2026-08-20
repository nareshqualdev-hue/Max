<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetaInfo extends Model
{	
    public $timestamps = false;
    
    protected $table = 'pu_meta_info'; 
    
    protected $fillable = ['meta_title','meta_keywords','meta_description','type'];
}