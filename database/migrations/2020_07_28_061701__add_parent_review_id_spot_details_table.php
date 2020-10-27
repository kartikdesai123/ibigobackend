<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddParentReviewIdSpotDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spot_details', function (Blueprint $table) {
            $table->bigInteger('parent_review_id')->unsigned()->nullable()->after('review_videos');
            $table->foreign('parent_review_id')
            ->references('id')->on('spot_details')
            ->onDelete('cascade');
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
