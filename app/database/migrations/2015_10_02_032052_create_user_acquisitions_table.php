<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserAcquisitionsTable extends Migration
{

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

        $builder->create('user_acquisitions', function (OrbitBlueprint $table) {
            $table->encodedId('user_acquisition_id');
            $table->encodedId('user_id');
            $table->encodedId('acquirer_id'); // mall (on mall) / merchant (on shop)
            $table->primary('user_acquisition_id');
            $table->unique(['user_id', 'acquirer_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('user_acquisitions');
    }

}
