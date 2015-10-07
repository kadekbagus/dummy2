<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableMemberships extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $conn = DB::connection();
        OrbitMySqlSchemaGrammar::useFor($conn);
        $builder = $conn->getSchemaBuilder();
        $builder->blueprintResolver(function ($table, $callback) {
            return new OrbitBlueprint($table, $callback);
        });
        $builder->create('memberships', function (OrbitBlueprint $table) {
            $table->encodedId('membership_id');
            $table->encodedId('merchant_id');
            $table->string('membership_name', 255);
            $table->string('description', 2000)->nullable();
            $table->string('status', 15);
            $table->encodedId('created_by')->nullable();
            $table->encodedId('modified_by')->nullable();
            $table->primary('membership_id');
            $table->timestamps();

            $table->index(array('merchant_id'), 'merchant_id_idx');
            $table->index(array('membership_name'), 'membership_name_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('created_by'), 'created_by_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('memberships');
    }

}
