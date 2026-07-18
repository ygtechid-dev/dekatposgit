<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalDetail extends Model
{
    protected $fillable = [
        "journal_id", "chart_of_account_id", "debit", "credit", "note"
    ];

    public function journal()
    {
        return $this->belongsTo('App\Models\Journal', 'journal_id');
    }

    public function account()
    {
        return $this->belongsTo('App\Models\ChartOfAccount', 'chart_of_account_id');
    }
}
