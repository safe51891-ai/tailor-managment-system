<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomerMeasurementsAndOrderDetails extends Migration
{
    public function up()
    {
        Schema::create('customer_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->date('measured_on');
            $table->string('taken_by', 100);
            $table->string('unit', 10);
            $table->string('convention', 20);
            $table->string('fit', 20);
            $table->json('values');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('garment', 20)->nullable();
            $table->string('status', 20)->default('received');
            $table->unsignedBigInteger('customer_measurement_id')->nullable();
            $table->json('measurement_snapshot')->nullable();
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['garment', 'status', 'customer_measurement_id', 'measurement_snapshot']);
        });
        Schema::dropIfExists('customer_measurements');
    }
}
