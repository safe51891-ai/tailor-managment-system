<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $casts = ['is_salary' => 'boolean'];

    public function expenses() { return $this->hasMany(Expense::class); }

    protected $fillable=[
        'name'
    ];
}
