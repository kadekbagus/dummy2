<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsAddColumnTimezoneId extends Migration {

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
        $builder->table('merchants', function(OrbitBlueprint $table)
        {
            $table->encodedId('timezone_id')->after('user_id');
            $table->index(array('timezone_id'), 'timezone_id_idx');
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
            $table->dropIndex('timezone_id_idx');
            $table->dropColumn('timezone_id');
        });
    }

}
