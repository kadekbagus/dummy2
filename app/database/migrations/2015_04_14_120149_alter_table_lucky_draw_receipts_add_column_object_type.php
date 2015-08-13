<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableLuckyDrawReceiptsAddColumnObjectType extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lucky_draw_receipts', function(Blueprint $table)
        {
            $table->string('object_type', 15)->nullable()->after('receipt_amount');

            $table->index(array('object_type'), 'object_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lucky_draw_receipts', function(Blueprint $table)
        {
            $table->dropIndex('object_type_idx');

            $table->dropColumn('object_type');
        });
    }

}
