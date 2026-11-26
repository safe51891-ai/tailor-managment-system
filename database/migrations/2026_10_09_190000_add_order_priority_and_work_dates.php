<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddOrderPriorityAndWorkDates extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('priority', ['normal', 'emergency'])->default('normal')->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });
        DB::table('orders')->orderBy('id')->chunkById(200, function ($orders) {
            foreach ($orders as $order) {
                $start = substr((string) $order->recieved_on, 0, 10);
                $end = substr((string) $order->collecting_on, 0, 10);
                $valid = function ($value) {
                    $parsed = \DateTime::createFromFormat('!Y-m-d', $value);
                    return $parsed && $parsed->format('Y-m-d') === $value;
                };
                if ($valid($start) && $valid($end) && $end >= $start) DB::table('orders')->where('id', $order->id)->update(['start_date' => $start, 'end_date' => $end]);
            }
        });
    }
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) { $table->dropColumn(['priority', 'start_date', 'end_date']); });
    }
}
