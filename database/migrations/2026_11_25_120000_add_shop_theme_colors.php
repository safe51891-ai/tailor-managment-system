<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShopThemeColors extends Migration
{
    public function up()
    {
        Schema::table('shop_profiles', function (Blueprint $table) {
            $table->string('primary_color', 7)->default('#176458');
            $table->string('sidebar_color', 7)->default('#182e36');
            $table->string('background_color', 7)->default('#f5f3ed');
        });
    }

    public function down()
    {
        Schema::table('shop_profiles', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'sidebar_color', 'background_color']);
        });
    }
}
