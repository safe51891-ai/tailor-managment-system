<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddShopFinanceCategories extends Migration
{
    public function up()
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->boolean('is_salary')->default(false);
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->constrained('staff');
        });
        foreach (['Shop rent', 'Electricity bills', 'Gas bills', 'Water bills', 'Internet / phone bills', 'Employee salaries', 'Tools', 'Sewing machines', 'Machine repairs / maintenance', 'Buttons', 'Buckram / Bukram', 'Thread', 'Needles', 'Zips / accessories', 'Fabric / lining', 'Other expenses'] as $name) {
            if (!DB::table('expense_categories')->where('name', $name)->exists()) {
                DB::table('expense_categories')->insert(['name' => $name, 'is_salary' => $name === 'Employee salaries', 'created_at' => now(), 'updated_at' => now()]);
            } elseif ($name === 'Employee salaries') {
                DB::table('expense_categories')->where('name', $name)->update(['is_salary' => true]);
            }
        }
        foreach (['Stitching payments', 'Alterations', 'Other income'] as $name) {
            if (!DB::table('income_categories')->where('name', $name)->exists()) DB::table('income_categories')->insert(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down()
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
        });
        Schema::table('expense_categories', function (Blueprint $table) { $table->dropColumn('is_salary'); });
        // Keep category records: existing financial entries may refer to them.
    }
}
