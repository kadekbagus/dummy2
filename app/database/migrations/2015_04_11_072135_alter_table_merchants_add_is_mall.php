<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsAddIsMall extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function(Blueprint $table)
        {
            $table->string('is_mall', 3)->nullable()->default('no')->after('parent_id');

            $table->index(array('is_mall'), 'is_mall');
            $table->index(array('is_mall', 'status'), 'is_mall_status_idx');
            $table->index(array('is_mall', 'status', 'object_type'), 'is_mall_status_object_idx');
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
            $table->dropIndex('is_mall');
            $table->dropIndex('is_mall_status_idx');
            $table->dropIndex('is_mall_status_object_idx');
            $table->dropColumn('is_mall');
        });
    }
}
