<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReceiptGroupsAndShopProfile extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('receipt_group_id')->nullable()->index();
        });
        Schema::create('shop_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 40)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('shop_profiles');
        Schema::table('orders', function (Blueprint $table) { $table->dropColumn('receipt_group_id'); });
    }
}
