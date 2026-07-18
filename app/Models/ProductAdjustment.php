<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAdjustment extends Model
{
    protected $table = 'product_adjustments';
    protected $fillable =[
        "adjustment_id", "product_id", "variant_id", "rack_id", "unit_cost", "qty", "action"
    ];

    public function rack()
    {
        return $this->belongsTo('App\Models\Rack');
    }
}
