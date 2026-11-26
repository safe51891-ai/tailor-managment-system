<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    public function measurements()
    {
        return $this->hasMany(CustomerMeasurement::class)->orderByDesc('measured_on')->orderByDesc('id');
    }

    public function orders()
    {
        return $this->hasMany(Orders::class);
    }

    protected $fillable=[
        'fullname',
        'address','phone','city','email',
        'comment','gender','date_recieved',
        
    ];
}
