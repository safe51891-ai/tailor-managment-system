<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDues extends Migration
{
    public function up()
    {
        Schema::create('dues', function (Blueprint $table) {
            $table->id();
            $table->enum('direction', ['receivable', 'payable'])->index();
            $table->string('person_name', 100);
            $table->string('phone', 16)->nullable();
            $table->string('category', 30);
            $table->string('description', 250)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->date('recorded_on');
            $table->date('due_on')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('due_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('due_id')->constrained('dues');
            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->uuid('submission_token')->unique();
            $table->foreignId('income_id')->nullable()->constrained('incomes');
            $table->foreignId('expense_id')->nullable()->constrained('expenses');
            $table->timestamps();
        });
    }
    public function down() { Schema::dropIfExists('due_payments'); Schema::dropIfExists('dues'); }
}
