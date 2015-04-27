<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableUserDetailsAddPhone3 extends Migration
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
            $table->string('phone3', 50)->nullable()->default(null)->after('phone2');
            $table->index(['phone3'], 'phone3_idx');
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
            $table->dropIndex('phone3_idx');
            $table->dropColumn('phone3');
        });
    }

}
