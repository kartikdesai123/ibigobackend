<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToSpotDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spot_details', function (Blueprint $table) {
            $table->tinyInteger('is_connected')->after('review')->nullable();
            $table->tinyInteger('is_like')->after('is_connected')->nullable();
            $table->string('invited_users')->after('is_like')->nullable();
            $table->string('tagged_users')->after('spot_id')->nullable();
            $table->datetime('checked_in_datetime')->after('review')->nullable()->change();
            $table->bigInteger('user_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spot_details', function (Blueprint $table) {
            //
        });
    }
}
