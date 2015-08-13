<?php
/**
 * @author Rio Astamal <me@rioastamal.net>
 */
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMerchantsAddFloorAndUnit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function(Blueprint $table)
        {
            $table->string('floor', 30)->nullable()->default(NULL)->after('ticket_footer');
            $table->string('unit', 30)->nullable()->default(NULL)->after('floor');

            $table->index(array('floor'), 'floor_idx');
            $table->index(array('unit'), 'unit_idx');
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
            $table->dropIndex('floor_idx');
            $table->dropIndex('unit_idx');

            $table->dropColumn('floor');
            $table->dropColumn('unit');
        });
    }
}
