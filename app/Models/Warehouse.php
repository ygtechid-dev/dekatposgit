<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable =[

        "name", "phone", "email", "address", "is_active", "type"
    ];

    public function product()
    {
    	return $this->hasMany('App\Models\Product');

    }

    public function racks()
    {
        return $this->hasMany('App\Models\Rack')->where('is_active', true);
    }
}
