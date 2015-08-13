<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableObjects extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('objects', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('object_id');
            $table->integer('merchant_id')->unsigned();
            $table->string('object_name', 50);
            $table->string('object_type', 50)->nullable();
            $table->string('status', 15);
            $table->timestamps();

            $table->index(array('object_id'), 'object_id_idx');
            $table->index(array('merchant_id'), 'merchant_id_idx');
            $table->index(array('object_name'), 'object_name_idx');
            $table->index(array('object_type'), 'object_type_idx');
            $table->index(array('status'), 'status_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('objects');
    }

}
