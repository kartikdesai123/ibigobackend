<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('notification_type')->nullable();
            $table->bigInteger('notification_to_user')->nullable();
            $table->bigInteger('notification_from_user')->nullable();
            $table->bigInteger('invited_spot_id')->nullable();
            $table->datetime('notification_time')->nullable();
            $table->tinyInteger('notification_read')->default('0')->nullable();
            $table->tinyInteger('is_read')->nullable();
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
        Schema::dropIfExists('notifications');
    }
}
