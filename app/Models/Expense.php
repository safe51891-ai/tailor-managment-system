<?php

namespace App\Models;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends Model
{
    use HasFactory;

    protected $fillable=[
        'expense_category_id',
        'description',
        'purchase_date','price','employee_id',
    ];

    public function duePayment() { return $this->hasOne(DuePayment::class, 'expense_id'); }

    public function employee() { return $this->belongsTo(Staff::class, 'employee_id')->withTrashed(); }

    public function expenseCategory(){
        return $this->belongsTo(ExpenseCategory::class);
    }
}
