<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableCouponTranslations extends Migration
{

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('coupon_translations', function(Blueprint $table)
		{
			$table->increments('coupon_translation_id');
            $table->integer('promotion_id')->unsigned();
            $table->integer('merchant_language_id')->unsigned();
            $table->string('promotion_name', 255)->nullable();
            $table->string('description', 2000)->nullable();
            $table->text('long_description')->nullable();
            $table->string('status', 15)->default('active');
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('modified_by')->unsigned()->nullable();
            $table->timestamps();
            $table->index(['promotion_id', 'merchant_language_id'], 'coupon_language_idx');
            $table->index(['status'], 'status_idx');
            $table->index(['created_by'], 'created_by_idx');
            $table->index(['modified_by'], 'modified_by_idx');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('coupon_translations');
	}

}
