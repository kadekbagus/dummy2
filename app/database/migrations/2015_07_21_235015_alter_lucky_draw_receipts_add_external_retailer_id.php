<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterLuckyDrawReceiptsAddExternalRetailerId extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lucky_draw_receipts', function(Blueprint $table)
        {
            $table->string('external_retailer_id', 30)->nullable()->after('external_receipt_id');
            $table->index(array('external_retailer_id'), 'external_retailer_idx');
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
            $table->dropIndex('external_retailer_idx');
            $table->dropColumn('external_retailer_id');
        });
    }
}
