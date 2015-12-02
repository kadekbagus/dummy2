<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsAddFieldLocationId extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function(Blueprint $table)
        {
            $table->char('location_id', 16)->after('object_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchants', function(Blueprint $table)
        {
            $table->dropColumn('location_id');
        });
    }

}
