<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterUserDetailAddDateOfWork extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_details', function(Blueprint $table)
        {
            $table->datetime('date_of_work')->nullable()->default(NULL)->after('occupation');

            $table->index(['date_of_work'], 'date_of_work_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_details', function(Blueprint $table)
        {
            $table->dropIndex('date_of_work_idx');
            $table->dropColumn('date_of_work');
        });
    }

}
