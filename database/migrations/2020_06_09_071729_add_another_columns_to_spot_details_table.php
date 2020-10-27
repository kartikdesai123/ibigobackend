<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAnotherColumnsToSpotDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spot_details', function (Blueprint $table) {
            $table->string('share_with_friends')->after('invited_users')->nullable();
            $table->text('photos')->after('share_with_friends')->nullable();
            $table->text('videos')->after('photos')->nullable();
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
