<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateForeignKeyInPlanningsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('plannings', function (Blueprint $table) {
            $table->bigInteger('user_id')->unsigned()->index()->change(); 
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->bigInteger('spot_id')->unsigned()->index()->change();
            $table->foreign('spot_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('plannings', function (Blueprint $table) {
            //
        });
    }
}
