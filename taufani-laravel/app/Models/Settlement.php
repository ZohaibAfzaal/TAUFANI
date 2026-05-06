<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    use HasFactory;
    protected $fillable = ['group_id', 'from_id', 'to_id', 'amount', 'date', 'note'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function from()
    {
        return $this->belongsTo(User::class, 'from_id');
    }

    public function to()
    {
        return $this->belongsTo(User::class, 'to_id');
    }
}
