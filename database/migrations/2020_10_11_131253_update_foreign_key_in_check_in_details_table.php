<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateForeignKeyInCheckInDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('check_in_details', function (Blueprint $table) {            
            $table->bigInteger('spot_id')->unsigned()->index()->change(); 
            $table->foreign('spot_id')->references('id')->on('users')->onDelete('cascade');

            $table->bigInteger('user_id')->unsigned()->index()->change(); 
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->bigInteger('group_id')->unsigned()->index()->change(); 
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('check_in_details', function (Blueprint $table) {
            //
        });
    }
}
