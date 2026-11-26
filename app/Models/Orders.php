<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Orders extends Model
{
    use HasFactory,SoftDeletes;

    public static function trackingCodeFor($id)
    {
        return 'TS-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    protected static function booted()
    {
        static::creating(function ($order) { $order->tracking_code = null; });
        static::created(function ($order) {
            $order->tracking_code = static::trackingCodeFor($order->id);
            // Use the database-assigned ID; parallel bookings cannot share a code.
            $order->getConnection()->table($order->getTable())->where('id', $order->id)
                ->update(['tracking_code' => $order->tracking_code]);
            $order->syncOriginalAttribute('tracking_code');
        });
        static::updating(function ($order) {
            if ($order->isDirty('receipt_group_id')) {
                throw new \LogicException('A saved garment cannot be moved to another receipt.');
            }
            if ($order->isDirty('tracking_code')) {
                throw new \LogicException('An order tracking code cannot be changed.');
            }
        });
    }

    protected $fillable =[
        'receipt_group_id',
        'customer_id','description','priority','start_date','end_date',
        'recieved_on','recieved_by','amount_charged',
        'amount_paid','collecting_on','garment','status',
        'customer_measurement_id','measurement_snapshot','karigar_id','quantity','stitching_styles','completed_at','pay_snapshot','employee_earning',
     ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'karigar_id' => 'integer', 'quantity' => 'integer', 'stitching_styles' => 'array', 'completed_at' => 'datetime', 'pay_snapshot' => 'array', 'employee_earning' => 'decimal:2', 'measurement_snapshot' => 'array', 'customer_id' => 'integer', 'customer_measurement_id' => 'integer'];
 
     public function karigar() { return $this->belongsTo(Staff::class, 'karigar_id')->withTrashed(); }

     public function receiptRootId() { return $this->receipt_group_id ?: $this->id; }

     public function receiptOrders()
     {
         $root = $this->receiptRootId();
         return static::with('customer', 'karigar')->where('customer_id', $this->customer_id)
             ->where(function ($query) use ($root) { $query->where('id', $root)->orWhere('receipt_group_id', $root); })
             ->orderBy('id')->get();
     }

     public function customer(){
         return $this->belongsTo(Customer::class);
     }
}
