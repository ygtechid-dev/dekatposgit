<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductUnitPriceLevel extends Model
{
    protected $fillable = [
        "product_unit_id", "customer_group_id", "markup_percent", "price"
    ];

    public function productUnit()
    {
        return $this->belongsTo('App\Models\ProductUnit');
    }

    public function customerGroup()
    {
        return $this->belongsTo('App\Models\CustomerGroup');
    }
}
