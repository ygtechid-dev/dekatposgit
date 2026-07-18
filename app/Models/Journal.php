<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    protected $fillable = [
        "journal_no", "journal_date", "description", "reference_type",
        "reference_id", "is_manual", "created_by"
    ];

    public function details()
    {
        return $this->hasMany('App\Models\JournalDetail', 'journal_id');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'created_by');
    }
}
