<?php

namespace App\Models;

use App\Models\Designation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Staff extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable=[
        'designation_id','fullname','address','gender',
        'phone','salary','avatar','pay_method','pay_rate','employment_start','is_karigar',
        'payment_account_type','account_number','bank_name','account_title'
    ];

    protected $hidden = ['account_number'];

    protected $casts = ['is_karigar' => 'boolean', 'employment_start' => 'date', 'pay_rate' => 'decimal:2'];

    public function payTerms() { return $this->hasMany(StaffPayTerm::class)->orderByDesc('effective_from'); }
    public function orders() { return $this->hasMany(Orders::class, 'karigar_id'); }

    public function designation(){
        return $this->belongsTo(Designation::class);
    }
}
