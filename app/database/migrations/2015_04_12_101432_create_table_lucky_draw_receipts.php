<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableLuckyDrawReceipts extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lucky_draw_receipts', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('lucky_draw_receipt_id');
            $table->integer('mall_id')->unsigned();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->integer('receipt_retailer_id')->unsigned()->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->datetime('receipt_date')->nullable();
            $table->string('receipt_payment_type', 30)->nullable();
            $table->string('receipt_card_number', 30)->nullable();
            $table->decimal('receipt_amount', 16, 2)->nullable()->default('0');
            $table->string('status', 15)->nullable();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('modified_by')->unsigned()->nullable();
            $table->timestamps();

            $table->index(array('mall_id'), 'mall_id_idx');
            $table->index(array('user_id'), 'user_id_idx');
            $table->index(array('receipt_retailer_id'), 'receipt_retailer_id_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('created_by'), 'created_by_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
            $table->index(array('created_at'), 'created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('lucky_draw_receipts');
    }

}
