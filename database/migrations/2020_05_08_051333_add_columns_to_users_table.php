<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->string('first_name',50)->after('id');
            $table->string('last_name',50)->after('first_name');
            $table->date('birth_date')->after('last_name');
            $table->string('user_profile')->after('birth_date');
            $table->string('mobile',20)->after('user_profile');
            $table->string('gender',15)->after('mobile');
            $table->string('user_interests')->after('gender');
            $table->tinyInteger('is_receive_commercial_email')->after('email');
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
            //
        });
    }
}
