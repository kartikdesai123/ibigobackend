<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpotPhotoVideosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spot_photo_videos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('spot_id')->unsigned();
            $table->text('user_to_spot_photos')->nullable();
            $table->text('user_to_spot_videos')->nullable();
            $table->bigInteger('user_id')->unsigned();
            $table->foreign('user_id')
              ->references('id')->on('users')
              ->onDelete('cascade');
            $table->foreign('spot_id')
              ->references('id')->on('users')
              ->onDelete('cascade');
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
        Schema::dropIfExists('spot_photo_videos');
    }
}
