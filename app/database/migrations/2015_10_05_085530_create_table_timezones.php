<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableTimezones extends Migration {

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
        $builder->create('timezones', function (OrbitBlueprint $table) {
            $table->encodedId('timezone_id');
            $table->string('timezone_name', 100);
            $table->string('timezone_offset', 9);
            $table->tinyInteger('timezone_order')->unsigned()->nullable();
            $table->primary('timezone_id');
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
        Schema::drop('timezones');
    }

}
