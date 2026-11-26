<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DuePayment extends Model
{
    protected $fillable = ['due_id', 'amount', 'paid_on', 'submission_token', 'income_id', 'expense_id'];
    protected $casts = ['amount' => 'decimal:2', 'paid_on' => 'date', 'due_id' => 'integer', 'income_id' => 'integer', 'expense_id' => 'integer'];
}
