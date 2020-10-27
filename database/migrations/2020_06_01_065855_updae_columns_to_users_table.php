<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdaeColumnsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name',50)->after('id')->nullable()->change();
            $table->string('last_name',50)->after('first_name')->nullable()->change();
            $table->date('birth_date')->after('last_name')->nullable()->change();
            $table->string('gender',15)->after('mobile')->nullable()->change();
            $table->string('user_interests')->after('gender')->nullable()->change();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
           
        });
    }
}
