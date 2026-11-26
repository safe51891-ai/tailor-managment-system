<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffPayTerm extends Model
{
    public $timestamps = false;
    protected $dateFormat = 'Y-m-d';
    protected $fillable = ['staff_id', 'effective_from', 'method', 'rate'];
    protected $casts = ['effective_from' => 'date', 'rate' => 'decimal:2'];
}
