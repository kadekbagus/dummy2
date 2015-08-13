<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableEventsRemoveColumns extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function(Blueprint $table)
        {
            $table->dropIndex('link_object_id1_idx');
            $table->dropIndex('link_object_id2_idx');
            $table->dropIndex('link_object_id3_idx');
            $table->dropIndex('link_object_id4_idx');
            $table->dropIndex('link_object_id5_idx');

            $table->dropColumn('link_object_id1');
            $table->dropColumn('link_object_id2');
            $table->dropColumn('link_object_id3');
            $table->dropColumn('link_object_id4');
            $table->dropColumn('link_object_id5');
            $table->dropColumn('widget_object_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function(Blueprint $table)
        {
            $table->bigInteger('link_object_id1')->unsigned()->nullable();
            $table->bigInteger('link_object_id2')->unsigned()->nullable();
            $table->bigInteger('link_object_id3')->unsigned()->nullable();
            $table->bigInteger('link_object_id4')->unsigned()->nullable();
            $table->bigInteger('link_object_id5')->unsigned()->nullable();
            $table->string('widget_object_type', 50)->nullable();

            $table->index(array('link_object_id1'), 'link_object_id1_idx');
            $table->index(array('link_object_id2'), 'link_object_id2_idx');
            $table->index(array('link_object_id3'), 'link_object_id3_idx');
            $table->index(array('link_object_id4'), 'link_object_id4_idx');
            $table->index(array('link_object_id5'), 'link_object_id5_idx');
        });
    }

}
