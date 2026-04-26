<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['group_id', 'description', 'amount', 'paid_by', 'split_type', 'date'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function participants()
    {
        return $this->belongsToMany(User::class)->withPivot('amount')->withTimestamps();
    }
}
