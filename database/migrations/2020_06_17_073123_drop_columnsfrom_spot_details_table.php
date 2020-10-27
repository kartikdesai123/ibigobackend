<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropColumnsfromSpotDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spot_details', function (Blueprint $table) {
            $table->dropColumn('tagged_users');
            $table->dropColumn('share_with_friends');
            $table->dropColumn('photos');
            $table->dropColumn('videos');
            $table->dropColumn('checked_in_datetime');
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
