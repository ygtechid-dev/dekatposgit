<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    protected $fillable = ["cash_in_hand", "user_id", "warehouse_id", "status",
        "settle_status", "approved_by", "approved_at", "closed_at", "settle_amount"];

    protected $casts = [
        'closed_at'   => 'datetime',
        'approved_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function user()
    {
    	return $this->belongsTo('App\Models\User');
    }

    public function warehouse()
    {
    	return $this->belongsTo('App\Models\Warehouse');
    }
}
