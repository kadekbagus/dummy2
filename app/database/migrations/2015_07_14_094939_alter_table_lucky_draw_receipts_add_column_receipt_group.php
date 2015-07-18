<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableLuckyDrawReceiptsAddColumnReceiptGroup extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lucky_draw_receipts', function(Blueprint $table)
        {
            $table->string('receipt_group', 40)->nullable()->after('receipt_amount');
            $table->index(['receipt_group'], 'receipt_group_idx');
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
            $table->dropIndex('receipt_group_idx');
            $table->dropColumn('receipt_group');
        });
    }

}
