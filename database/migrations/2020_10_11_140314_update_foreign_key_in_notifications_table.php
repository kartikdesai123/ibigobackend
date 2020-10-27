<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateForeignKeyInNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('notifications', function (Blueprint $table) {
            /*
            post_id bigint(20) NULL             
            invited_spot_id bigint(20) NULL 
            invited_group_id
            */
            $table->bigInteger('notification_to_user')->unsigned()->index()->change(); 
            $table->foreign('notification_to_user')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('notification_from_user')->unsigned()->index()->change(); 
            $table->foreign('notification_from_user')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('invited_group_id')->unsigned()->index()->change(); 
            $table->foreign('invited_group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->bigInteger('invited_spot_id')->unsigned()->index()->change(); 
            $table->foreign('invited_spot_id')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('post_id')->unsigned()->index()->change(); 
            $table->foreign('post_id')->references('id')->on('check_in_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notifications', function (Blueprint $table) {
            //
        });
    }
}
