<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable =[
        "code", "name", "is_active", "chart_of_account_id"
    ];

    public function expense() {
    	return $this->hasMany('App\Models\Expense');
    }

    public function chartOfAccount()
    {
        return $this->belongsTo('App\Models\ChartOfAccount', 'chart_of_account_id');
    }
}
