<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmployeePaymentAccounts extends Migration
{
    public function up()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('payment_account_type', 20)->nullable();
            $table->string('account_number', 34)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('account_title', 100)->nullable();
        });
    }
    public function down()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['payment_account_type', 'account_number', 'bank_name', 'account_title']);
        });
    }
}
