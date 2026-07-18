<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rack extends Model
{
    protected $fillable = [
        "warehouse_id", "name", "is_active"
    ];

    public function warehouse()
    {
        return $this->belongsTo('App\Models\Warehouse');
    }
}
