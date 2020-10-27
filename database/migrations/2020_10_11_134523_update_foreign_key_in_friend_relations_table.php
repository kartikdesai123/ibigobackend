<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateForeignKeyInFriendRelationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('friend_relations', function (Blueprint $table) {
            $table->bigInteger('from_user_id')->unsigned()->index()->change(); 
            $table->foreign('from_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->bigInteger('to_user_id')->unsigned()->index()->change(); 
            $table->foreign('to_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->bigInteger('action_user_id')->unsigned()->index()->change(); 
            $table->foreign('action_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('friend_relations', function (Blueprint $table) {
            //
        });
    }
}
