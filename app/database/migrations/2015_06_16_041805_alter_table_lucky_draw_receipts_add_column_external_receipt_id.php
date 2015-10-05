<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableLuckyDrawReceiptsAddColumnExternalReceiptId extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lucky_draw_receipts', function(Blueprint $table)
        {
            $table->string('external_receipt_id', 50)->nullable()->after('receipt_amount');
            $table->index(['external_receipt_id'], 'external_receipt_id_idx');
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
            $table->dropIndex('external_receipt_id_idx');
            $table->dropColumn('external_receipt_id');
        });
    }

}
