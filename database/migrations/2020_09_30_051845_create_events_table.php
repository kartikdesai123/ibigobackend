<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventsTable extends Migration
{
    /**
     * Run the migrations.
     * 
     * @return void
     */
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('event_title')->nullable();
            $table->datetime('start_date_time')->nullable();
            $table->datetime('end_date_time')->nullable();
            $table->text('event_description')->nullable();
            $table->string('event_category')->nullable();
            $table->string('location')->nullable();
            $table->string('event_cover')->nullable();
            $table->bigInteger('host_id')->nullable();
            $table->bigInteger('host_group')->nullable();
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
        Schema::dropIfExists('events');
    }
}
