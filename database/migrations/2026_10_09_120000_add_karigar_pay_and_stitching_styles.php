<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddKarigarPayAndStitchingStyles extends Migration
{
    public function up()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('pay_method', 20)->default('salary');
            $table->decimal('pay_rate', 12, 2)->default(0);
            $table->date('employment_start')->nullable();
            $table->boolean('is_karigar')->default(true);
        });
        Schema::create('staff_pay_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff');
            $table->date('effective_from');
            $table->string('method', 20);
            $table->decimal('rate', 12, 2);
            $table->unique(['staff_id', 'effective_from']);
        });
        foreach (DB::table('staff')->get() as $employee) {
            $rate = is_numeric($employee->salary) ? max(0, min(999999999.99, (float) $employee->salary)) : 0;
            $start = substr($employee->created_at ?: now()->toDateString(), 0, 10);
            DB::table('staff')->where('id', $employee->id)->update(['pay_rate' => $rate, 'employment_start' => $start]);
            DB::table('staff_pay_terms')->insert(['staff_id' => $employee->id, 'effective_from' => $start, 'method' => 'salary', 'rate' => $rate]);
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('karigar_id')->nullable()->constrained('staff');
            $table->unsignedInteger('quantity')->default(1);
            $table->json('stitching_styles')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->json('pay_snapshot')->nullable();
            $table->decimal('employee_earning', 12, 2)->default(0);
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['karigar_id']);
            $table->dropColumn(['karigar_id', 'quantity', 'stitching_styles', 'completed_at', 'pay_snapshot', 'employee_earning']);
        });
        Schema::dropIfExists('staff_pay_terms');
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['pay_method', 'pay_rate', 'employment_start', 'is_karigar']);
        });
    }
}
