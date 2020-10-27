<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateForeignKeyInPostCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('post_comments', function (Blueprint $table) {
            $table->bigInteger('comment_user_id')->unsigned()->index()->change();
            $table->foreign('comment_user_id')->references('id')->on('users')->onDelete('cascade');

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
        Schema::table('post_comments', function (Blueprint $table) {
            //
        });
    }
}
