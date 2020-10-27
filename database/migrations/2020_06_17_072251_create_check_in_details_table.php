<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCheckInDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('check_in_details', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('spot_id')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->text('spot_description')->nullable();
            $table->string('tagged_users')->nullable();
            $table->string('share_with_friends')->nullable();
            $table->text('photos')->nullable();
            $table->text('videos')->nullable();
            $table->datetime('checked_in_datetime')->nullable();
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
        Schema::dropIfExists('check_in_details');
    }
}
