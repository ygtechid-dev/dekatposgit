<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    protected $fillable = [
        "cash_in_hand",
        "user_id",
        "warehouse_id",
        "status",
        "expected_cash_amount",
        "closing_cash_amount",
        "deposit_amount",
        "cash_difference",
        "closing_note",
        "approval_status",
        "approved_by",
        "approved_at",
        "approval_note",
    ];

    public function user()
    {
    	return $this->belongsTo('App\Models\User');
    }

    public function warehouse()
    {
    	return $this->belongsTo('App\Models\Warehouse');
    }

    public function approver()
    {
        return $this->belongsTo('App\Models\User', 'approved_by');
    }
}
