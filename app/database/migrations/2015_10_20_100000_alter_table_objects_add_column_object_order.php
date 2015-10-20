<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableObjectsAddColumnObjectOrder extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('objects', function(Blueprint $table)
        {
            $table->integer('object_order')->unsigned()->nullable()->after('object_type');
            $table->index(array('object_order'), 'object_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('objects', function(Blueprint $table)
        {
            $table->dropIndex('object_order_idx');
            $table->dropColumn('object_order');
        });
    }

}
