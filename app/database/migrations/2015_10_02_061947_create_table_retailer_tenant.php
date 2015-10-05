<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableRetailerTenant extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('retailer_tenant', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->char('retailer_tenant_id', 16)->primary();
            $table->char('retailer_id', 16);
            $table->char('tenant_id', 16);
            $table->timestamps();

            $table->index(array('retailer_id'), 'retailer_id_idx');
            $table->index(array('tenant_id'), 'tenant_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('retailer_tenant');
    }

}
