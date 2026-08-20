<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopBar extends Model
{
    use HasFactory;
    
    protected $table = 'pu_top_bars';
    
    protected $primaryKey = 'BarId';
    
    public $incrementing = false;
    
    protected $keyType = 'int';
    
    public $timestamps = false;
    
    protected $fillable = [
        'BarId',
        'bar_type',
        'link_title',
        'link',
        'status',
    ];
    
    protected $casts = [
        'BarId' => 'integer',
        'bar_type' => 'integer',
        'link_title' => 'string',
        'link' => 'string',
        'status' => 'string',
    ];
}
?>