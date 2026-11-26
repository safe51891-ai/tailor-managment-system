<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomeCategory extends Model
{
    use HasFactory;

    public function incomes() { return $this->hasMany(Income::class); }

    protected $fillable=[
        'name'
    ];
}
