<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsAddColumnExternalObjectId extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function(Blueprint $table)
        {
            $table->string('external_object_id', 50)->nullable()->after('unit');
            $table->index(['external_object_id'], 'external_object_id_idx');
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
            $table->dropIndex('external_object_id_idx');
            $table->dropColumn('external_object_id');
        });
    }

}
