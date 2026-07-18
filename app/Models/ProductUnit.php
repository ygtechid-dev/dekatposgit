<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model
{
    protected $fillable = [
        "product_id", "unit_id", "cost", "markup_percent", "price", "point", "commission", "sort_order"
    ];

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }

    public function unit()
    {
        return $this->belongsTo('App\Models\Unit');
    }

    public function priceLevels()
    {
        return $this->hasMany('App\Models\ProductUnitPriceLevel');
    }

    public function priceTiers()
    {
        return $this->hasMany('App\Models\ProductUnitPriceTier')->orderBy('tier_no');
    }
}
