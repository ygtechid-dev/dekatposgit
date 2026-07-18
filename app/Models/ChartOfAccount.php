<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    protected $fillable = [
        "code", "name", "type", "normal_balance", "parent_id",
        "opening_balance", "description", "is_default", "is_active", "is_cash_account"
    ];

    public function parent()
    {
        return $this->belongsTo('App\Models\ChartOfAccount', 'parent_id');
    }

    public function children()
    {
        return $this->hasMany('App\Models\ChartOfAccount', 'parent_id');
    }

    public function journalDetails()
    {
        return $this->hasMany('App\Models\JournalDetail', 'chart_of_account_id');
    }

    /**
     * The legacy `accounts` (Daftar Akun) row this Kode Akun was provisioned
     * for, when it is flagged as a Kas/Bank account.
     */
    public function account()
    {
        return $this->hasOne('App\Models\Account', 'chart_of_account_id');
    }
}
