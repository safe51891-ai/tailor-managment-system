<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Due extends Model
{
    use SoftDeletes;
    protected $fillable = ['direction', 'person_name', 'phone', 'category', 'description', 'amount', 'paid_amount', 'recorded_on', 'due_on'];
    protected $casts = ['amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'recorded_on' => 'date', 'due_on' => 'date'];
    public function payments() { return $this->hasMany(DuePayment::class)->orderByDesc('paid_on')->orderByDesc('id'); }
    public function getBalanceAttribute() { return (round($this->amount * 100) - round($this->paid_amount * 100)) / 100; }
    public static function categories() { return ['customer' => 'Other customer dues', 'supplier' => 'Supplier / materials', 'rent' => 'Rent / bills', 'salary' => 'Employee pay', 'loan' => 'Loan / advance', 'other' => 'Other']; }
}
