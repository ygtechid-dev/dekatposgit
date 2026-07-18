<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductUnitPriceTier extends Model
{
    protected $fillable = [
        "product_unit_id", "tier_no", "min_qty", "markup_percent", "price"
    ];

    public function productUnit()
    {
        return $this->belongsTo('App\Models\ProductUnit');
    }
}
