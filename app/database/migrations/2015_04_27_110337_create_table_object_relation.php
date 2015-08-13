<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableObjectRelation extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('object_relation', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('object_relation_id');
            $table->bigInteger('main_object_id')->unsigned();
            $table->string('main_object_type', 50)->nullable();
            $table->bigInteger('secondary_object_id')->unsigned();
            $table->string('secondary_object_type', 50)->nullable();

            $table->index(array('main_object_id'), 'main_object_id_idx');
            $table->index(array('main_object_type'), 'main_object_type_idx');
            $table->index(array('secondary_object_id'), 'secondary_object_id_idx');
            $table->index(array('secondary_object_type'), 'secondary_object_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('object_relation');
    }

}
