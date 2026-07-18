<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable =[
        "account_no", "name", "initial_balance", "total_balance", "note", "is_default", "is_active", "chart_of_account_id"
    ];

    public function chartOfAccount()
    {
        return $this->belongsTo('App\Models\ChartOfAccount', 'chart_of_account_id');
    }

    /**
     * Kode Akun (Chart of Account code) tied to this cash/bank account, falling
     * back to the account_no if it was never linked to a COA.
     */
    public function getCoaLabelCodeAttribute()
    {
        return $this->chartOfAccount ? $this->chartOfAccount->code : $this->account_no;
    }

    /**
     * Display name for this cash/bank account, preferring the Chart of Account
     * name so every dropdown/report reads consistently off Kode Akun.
     */
    public function getCoaLabelNameAttribute()
    {
        return $this->chartOfAccount ? $this->chartOfAccount->name : $this->name;
    }

    /**
     * "code - name" label used in every account picker dropdown across the app.
     */
    public function getCoaLabelAttribute()
    {
        return $this->coa_label_code . ' - ' . $this->coa_label_name;
    }
}
