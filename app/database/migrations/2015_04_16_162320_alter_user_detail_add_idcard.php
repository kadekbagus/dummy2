<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterUserDetailAddIdcard extends Migration
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
            $table->string('idcard', '30')->nullable()->default(NULL)->after('relationship_status');

            $table->index(['idcard'], 'idcard_idx');
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
            $table->dropIndex('idcard_idx');
            $table->dropColumn('idcard');
        });
    }

}
