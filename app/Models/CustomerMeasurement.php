<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerMeasurement extends Model
{
    protected $fillable = ['customer_id', 'measured_on', 'taken_by', 'unit', 'convention', 'fit', 'values', 'notes'];

    protected $casts = ['values' => 'array', 'measured_on' => 'date'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function snapshot()
    {
        return $this->only(['measured_on', 'taken_by', 'unit', 'convention', 'fit', 'values', 'notes']);
    }
}
