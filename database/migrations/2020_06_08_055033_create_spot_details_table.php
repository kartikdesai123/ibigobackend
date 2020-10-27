<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpotDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spot_details', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('spot_id');
            $table->string('user_id')->nullable();
            $table->string('rating',5)->nullable();
            $table->text('review')->nullable();
            $table->datetime('checked_in_datetime');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('spot_details');
    }
}
