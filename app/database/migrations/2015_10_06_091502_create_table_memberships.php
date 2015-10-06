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
            $table->encodedId('media_id')->nullable();
            $table->string('status', 15);
            $table->primary('membership_id');
            $table->timestamps();

            $table->index(array('merchant_id'), 'merchant_id_idx');
            $table->index(array('membership_name'), 'membership_name_idx');
            $table->index(array('media_id'), 'media_id_idx');
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
        Schema::drop('memberships');
    }

}
